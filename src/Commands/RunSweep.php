<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Commands;

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\InvalidQuery;
use Bpmore\Sweep\Engine\Mode;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Sweeps\Status;
use Bpmore\Sweep\Sweeps\Sweep;
use Bpmore\Sweep\Sweeps\Sweeps;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Console\Command;

/**
 * Search and replace from the command line, for CI and for sites too large
 * to wait on a browser. Runs as nobody in particular: every item in scope is
 * searched, whatever any one person may edit.
 */
class RunSweep extends Command
{
    protected $signature = 'sweep:run
        {--find= : The text, or with --regex the pattern, to find}
        {--replace= : What to put in its place}
        {--regex : Read --find as a regular expression; use $1, $2 in --replace}
        {--word : Match whole words only}
        {--ignore-case : Match regardless of case}
        {--preserve-case : Give each replacement the case of what it replaces}
        {--scope=* : Where to look: collection:blog, taxonomy:tags, global:footer, nav:main, assets:images, site:en, status:draft, field:title. Repeat for more}
        {--slugs : Also search slug fields. Changing a slug changes a URL}
        {--code : Also search code fields}
        {--dry-run : Write the preview and print the counts, changing nothing (the default)}
        {--apply : Apply the preview: the one this run makes, or with --sweep an earlier one}
        {--sweep= : The id of an earlier preview, to apply with --apply}';

    protected $description = 'Find and replace across the site, writing a preview first';

    public function handle(Sweeps $sweeps, SweepStore $store): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->components->error(__('sweep::messages.cli_apply_and_dry_run'));

            return self::INVALID;
        }

        if ($this->option('sweep') !== null) {
            return $this->applyEarlier($sweeps, $store, (string) $this->option('sweep'));
        }

        try {
            $query = new Query(
                find: (string) $this->option('find'),
                replace: (string) $this->option('replace'),
                mode: $this->option('regex') ? Mode::Regex : ($this->option('word') ? Mode::WholeWord : Mode::Literal),
                caseSensitive: ! $this->option('ignore-case'),
                preserveCase: (bool) $this->option('preserve-case'),
            );
            $scope = Scope::fromStrings(array_values((array) $this->option('scope')), (bool) $this->option('slugs'), (bool) $this->option('code'));
        } catch (InvalidQuery $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        if ($this->option('regex') && $this->option('word')) {
            $this->components->error(__('sweep::messages.cli_regex_and_word'));

            return self::INVALID;
        }

        $sweep = $sweeps->start($query, $scope, null, now: true);

        if ($sweep->status === Status::Failed) {
            $this->components->error((string) $sweep->error);

            return self::FAILURE;
        }

        $this->counts($sweep);

        if (! $this->option('apply')) {
            $this->components->info(__('sweep::messages.cli_previewed', ['id' => $sweep->id]));

            return self::SUCCESS;
        }

        return $this->apply($sweeps, $store, $sweep);
    }

    private function applyEarlier(Sweeps $sweeps, SweepStore $store, string $id): int
    {
        $sweep = $store->find($id);

        if ($sweep === null) {
            $this->components->error(__('sweep::messages.cli_no_such_sweep', ['id' => $id]));

            return self::INVALID;
        }

        if (! $this->option('apply')) {
            $this->counts($sweep);

            return self::SUCCESS;
        }

        return $this->apply($sweeps, $store, $sweep);
    }

    private function apply(Sweeps $sweeps, SweepStore $store, Sweep $sweep): int
    {
        if (! $sweeps->apply($sweep, null, now: true)) {
            $this->components->error(__('sweep::messages.cli_not_applicable', ['status' => $sweep->status->value]));

            return self::FAILURE;
        }

        $sweep = $store->find($sweep->id) ?? $sweep;

        if ($sweep->status === Status::Failed) {
            $this->components->error((string) $sweep->error);

            return self::FAILURE;
        }

        foreach ($sweep->applyTally as $outcome => $count) {
            if ($count > 0) {
                $this->components->twoColumnDetail(__('sweep::messages.outcome_'.$outcome), (string) $count);
            }
        }

        $this->components->info(__('sweep::messages.cli_applied', ['id' => $sweep->id]));

        return self::SUCCESS;
    }

    private function counts(Sweep $sweep): void
    {
        $tally = $sweep->tally;

        $this->components->twoColumnDetail(__('sweep::messages.count_searched'), (string) $tally->searched);
        $this->components->twoColumnDetail(__('sweep::messages.count_matched_items'), (string) $tally->matchedItems);
        $this->components->twoColumnDetail(__('sweep::messages.count_matches'), (string) $tally->matches);
        $this->components->twoColumnDetail(__('sweep::messages.count_splits'), (string) $tally->splits);

        foreach ($tally->failures as $failure) {
            $this->components->warn(__('sweep::messages.cli_item_failed', ['title' => $failure['title'], 'message' => $failure['message']]));
        }
    }
}
