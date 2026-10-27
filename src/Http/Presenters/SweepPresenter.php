<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Http\Presenters;

use Bpmore\Sweep\Sweeps\Sweep;
use Bpmore\Sweep\Sweeps\Sweeps;
use Bpmore\Sweep\Sweeps\SweepStore;
use Statamic\Facades\Site;

/**
 * Sweeps as the control panel shows them.
 */
final class SweepPresenter
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly SweepStore $store,
        private readonly Sweeps $sweeps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Sweep $sweep): array
    {
        return [
            'id' => $sweep->id,
            'status' => $sweep->status->value,
            'working' => $sweep->status->isWorking(),
            'find' => $sweep->query->find,
            'replace' => $sweep->query->replace,
            'mode' => $sweep->query->mode->value,
            'case_sensitive' => $sweep->query->caseSensitive,
            'preserve_case' => $sweep->query->preserveCase,
            'scope' => $this->scope($sweep),
            'created_by' => $sweep->createdBy['name'] ?? __('sweep::messages.artisan'),
            'created_at' => $sweep->createdAt,
            'applied_by' => $sweep->appliedBy['name'] ?? ($sweep->appliedAt ? __('sweep::messages.artisan') : null),
            'applied_at' => $sweep->appliedAt,
            'undone_by' => $sweep->undoneBy['name'] ?? ($sweep->undoneAt ? __('sweep::messages.artisan') : null),
            'undone_at' => $sweep->undoneAt,
            'tally' => $sweep->tally->toArray(),
            'apply_tally' => $sweep->applyTally,
            'undo_tally' => $sweep->undoTally,
            'progress' => $sweep->progress,
            'items' => $sweep->items,
            'error' => $sweep->error,
            'pruned' => $sweep->pruned,
            'undoable' => $this->sweeps->undoable($sweep),
            'url' => cp_route('sweep.sweeps.show', $sweep->id),
        ];
    }

    /**
     * One page of a sweep's items, read from the one chunk it is in.
     *
     * @return array{data: list<array<string, mixed>>, page: int, pages: int, total: int}
     */
    public function page(Sweep $sweep, int $page): array
    {
        $pages = max(1, (int) ceil($sweep->items / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $first = ($page - 1) * self::PER_PAGE;
        $chunk = intdiv($first, SweepStore::CHUNK) + 1;
        $records = array_slice($this->store->readChunk($sweep->id, $chunk), $first % SweepStore::CHUNK, self::PER_PAGE, true);

        $data = [];
        foreach ($records as $offset => $record) {
            $data[] = $this->item(($chunk - 1) * SweepStore::CHUNK + $offset, $record);
        }

        return ['data' => $data, 'page' => $page, 'pages' => $pages, 'total' => $sweep->items];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function item(int $index, array $record): array
    {
        return [
            'index' => $index,
            'reference' => $record['reference'],
            'kind' => $record['kind'],
            'site' => $record['site'] && Site::multiEnabled() ? (Site::get($record['site'])?->name() ?? $record['site']) : null,
            'title' => $record['title'],
            'edit_url' => $record['edit_url'],
            'selected' => ($record['selected'] ?? true) !== false,
            'outcome' => $record['outcome'] ?? null,
            'outcome_message' => $record['outcome_message'] ?? null,
            'undo' => $record['undo'] ?? null,
            'changes' => array_map(static fn (array $change): array => [
                'label' => Label::words($change['label'] ?? []),
                'undo' => $change['undo'] ?? null,
                'hits' => array_map(static fn (array $hit): array => [
                    'before' => $hit['context_before'] ?? '',
                    'text' => $hit['text'],
                    'replacement' => $hit['replacement'],
                    'after' => $hit['context_after'] ?? '',
                    'selected' => ($hit['selected'] ?? true) !== false,
                ], $change['hits'] ?? []),
            ], $record['changes'] ?? []),
            'splits' => array_map(static fn (array $split): array => [
                'label' => Label::words($split['label'] ?? []),
                'text' => $split['text'],
                'context' => $split['context'],
            ], $record['splits'] ?? []),
        ];
    }

    /** The scope in a sentence. */
    private function scope(Sweep $sweep): string
    {
        $scope = $sweep->scope;
        $parts = [];

        foreach (['collections', 'taxonomies', 'globals', 'navs', 'containers'] as $kind) {
            $value = $scope->{$kind};

            if ($value === []) {
                continue;
            }

            $parts[] = $value === null
                ? __("sweep::messages.scope_all_{$kind}")
                : __("sweep::messages.scope_some_{$kind}", ['handles' => implode(', ', $value)]);
        }

        $text = $parts === [] ? __('sweep::messages.scope_nothing') : implode('; ', $parts);

        if ($scope->sites !== []) {
            $text .= '. '.__('sweep::messages.scope_sites', ['sites' => implode(', ', $scope->sites)]);
        }

        if ($scope->statuses !== []) {
            $text .= '. '.__('sweep::messages.scope_statuses', ['statuses' => implode(', ', $scope->statuses)]);
        }

        if ($scope->handles !== []) {
            $text .= '. '.__('sweep::messages.scope_fields', ['fields' => implode(', ', $scope->handles)]);
        }

        return $text;
    }
}
