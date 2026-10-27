<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Sweeps;

use Bpmore\Sweep\Content\Fingerprint;
use Bpmore\Sweep\Content\Item;
use Bpmore\Sweep\Content\Items;
use Bpmore\Sweep\Engine\Hit;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Paths;
use Closure;
use Illuminate\Support\Carbon;
use RuntimeException;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\Git;
use Statamic\Git\Subscriber as GitSubscriber;
use Throwable;

/**
 * Writes a previewed sweep to the site, and puts an applied one back.
 *
 * Each item is read again just before it is written. If anything in it is
 * different from what the preview saw, somebody edited it in between, and it
 * is skipped and flagged rather than written over.
 *
 * Git automation: Statamic commits after every content save, which for a
 * 200-item sweep is 200 commits. Its Git subscriber is switched off around
 * the batch and one commit is dispatched at the end, with the sweep's
 * message (Statamic\Git\Subscriber::disable() and enable(), then
 * Git::dispatchCommit(), checked against Statamic 6.38). Every other
 * listener still runs on every save: validation listeners, search indexing,
 * and static cache invalidation, which honours the site's own invalidation
 * rules for each item.
 */
final class Applier
{
    /** Outcomes an item can end an apply with. */
    public const APPLIED = 'applied';

    public const DESELECTED = 'deselected';

    public const CHANGED = 'changed_since_preview';

    public const MISSING = 'missing';

    public const NOT_PERMITTED = 'not_permitted';

    public const WORKING_COPY = 'working_copy';

    public const FAILED = 'failed';

    /** Outcomes an item can end an undo with. */
    public const RESTORED = 'restored';

    public const PARTIAL = 'partial';

    public const CONFLICT = 'conflict';

    public function __construct(
        private readonly SweepStore $store,
        private readonly Items $items,
    ) {}

    public static function message(Sweep $sweep): string
    {
        return "Sweep: {$sweep->query->find} → {$sweep->query->replace}";
    }

    public static function undoMessage(Sweep $sweep): string
    {
        return "Undo sweep: {$sweep->query->find} → {$sweep->query->replace}";
    }

    public function apply(string $id, ?User $user): void
    {
        $sweep = $this->store->find($id);

        if ($sweep === null || $sweep->status !== Status::Applying) {
            return;
        }

        $tally = array_fill_keys([self::APPLIED, self::DESELECTED, self::CHANGED, self::MISSING, self::NOT_PERMITTED, self::WORKING_COPY, self::FAILED], 0);
        $done = 0;

        $wrote = $this->batch(function () use ($sweep, $user, &$tally, &$done): bool {
            $wrote = false;

            for ($chunk = 1; $chunk <= $sweep->chunks; $chunk++) {
                $this->store->updateChunk($sweep->id, $chunk, function (array $records) use ($sweep, $user, &$tally, &$wrote): array {
                    foreach ($records as $i => $record) {
                        $records[$i] = $this->applyOne($sweep, $record, $user);
                        $tally[$records[$i]['outcome']]++;
                        $wrote = $wrote || $records[$i]['outcome'] === self::APPLIED;
                    }

                    return $records;
                });

                $done += count($this->store->readChunk($sweep->id, $chunk));
                $sweep->progress = ['phase' => 'apply', 'done' => $done, 'total' => $sweep->items];
                $sweep->applyTally = $tally;
                $this->store->save($sweep);
            }

            return $wrote;
        });

        if ($wrote) {
            $this->commit(self::message($sweep), $user);
        }

        $sweep->status = Status::Applied;
        $sweep->applyTally = $tally;
        $sweep->appliedAt = Carbon::now()->toIso8601String();
        $sweep->appliedBy = Sweeps::person($user);
        $this->store->save($sweep);
    }

    /**
     * Puts back every string the sweep changed that nobody has edited since.
     * A string that is different now is a conflict: it is left as it is and
     * listed, because putting the old text back would throw away somebody's
     * edit.
     */
    public function undo(string $id, ?User $user): void
    {
        $sweep = $this->store->find($id);

        if ($sweep === null || $sweep->status !== Status::Undoing) {
            return;
        }

        $tally = array_fill_keys([self::RESTORED, self::PARTIAL, self::CONFLICT, self::MISSING, self::NOT_PERMITTED, self::FAILED], 0);
        $done = 0;

        $wrote = $this->batch(function () use ($sweep, $user, &$tally, &$done): bool {
            $wrote = false;

            for ($chunk = 1; $chunk <= $sweep->chunks; $chunk++) {
                $this->store->updateChunk($sweep->id, $chunk, function (array $records) use ($sweep, $user, &$tally, &$wrote, &$done): array {
                    foreach ($records as $i => $record) {
                        $done++;

                        if (($record['outcome'] ?? null) !== self::APPLIED || isset($record['undo'])) {
                            continue;
                        }

                        $records[$i] = $this->undoOne($sweep, $record, $user);
                        $tally[$records[$i]['undo']]++;
                        $wrote = $wrote || in_array($records[$i]['undo'], [self::RESTORED, self::PARTIAL], true);
                    }

                    return $records;
                });

                $sweep->progress = ['phase' => 'undo', 'done' => $done, 'total' => $sweep->items];
                $sweep->undoTally = $tally;
                $this->store->save($sweep);
            }

            return $wrote;
        });

        if ($wrote) {
            $this->commit(self::undoMessage($sweep), $user);
        }

        $leftOver = $tally[self::PARTIAL] + $tally[self::CONFLICT] + $tally[self::MISSING] + $tally[self::NOT_PERMITTED] + $tally[self::FAILED];

        $sweep->status = $leftOver === 0 ? Status::Undone : Status::PartiallyUndone;
        $sweep->undoTally = $tally;
        $sweep->undoneAt = Carbon::now()->toIso8601String();
        $sweep->undoneBy = Sweeps::person($user);
        $this->store->save($sweep);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function undoOne(Sweep $sweep, array $record, ?User $user): array
    {
        $item = $this->items->findFor((string) ($record['reference_after'] ?? $record['reference']), $sweep->scope);

        if ($item === null) {
            return [...$record, 'undo' => self::MISSING];
        }

        if ($user !== null && ! $item->mayEdit($user)) {
            return [...$record, 'undo' => self::NOT_PERMITTED];
        }

        $data = $item->data();
        $restored = 0;
        $conflicts = 0;

        foreach ($record['changes'] as $c => $change) {
            if (! isset($change['after_hash'])) {
                continue;
            }

            $current = Paths::get($data, $change['path']);
            $before = null;

            if (is_string($current) && SweepStore::hash($current) === $change['after_hash']) {
                try {
                    $before = Matcher::revert($current, self::selectedHits($change));
                } catch (RuntimeException) {
                    $before = null;
                }
            }

            // Rebuilt, then checked against what the preview saw: the bytes
            // put back are the bytes that were there, or nothing is.
            if ($before === null || SweepStore::hash($before) !== $change['before_hash']) {
                $record['changes'][$c]['undo'] = self::CONFLICT;
                $conflicts++;

                continue;
            }

            $data = Paths::set($data, $change['path'], $before);
            $record['changes'][$c]['undo'] = self::RESTORED;
            $restored++;
        }

        if ($restored === 0) {
            return [...$record, 'undo' => self::CONFLICT];
        }

        try {
            $item->write($data, self::undoMessage($sweep), $user);
        } catch (Throwable $e) {
            foreach ($record['changes'] as $c => $change) {
                if (($change['undo'] ?? null) === self::RESTORED) {
                    unset($record['changes'][$c]['undo']);
                }
            }

            return [...$record, 'undo' => self::FAILED, 'undo_message' => $e->getMessage()];
        }

        return [...$record, 'undo' => $conflicts === 0 ? self::RESTORED : self::PARTIAL];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function applyOne(Sweep $sweep, array $record, ?User $user): array
    {
        $record['outcome_message'] = null;

        if (($record['selected'] ?? true) === false || ! self::anySelected($record)) {
            return [...$record, 'outcome' => self::DESELECTED];
        }

        $item = $this->items->findFor((string) $record['reference'], $sweep->scope);

        if ($item === null) {
            return [...$record, 'outcome' => self::MISSING];
        }

        if ($user !== null && ! $item->mayEdit($user)) {
            return [...$record, 'outcome' => self::NOT_PERMITTED];
        }

        if ($reason = $item->cannotWrite()) {
            return [...$record, 'outcome' => $reason === 'working_copy' ? self::WORKING_COPY : self::FAILED];
        }

        $data = $item->data();

        if (Fingerprint::of($data) !== $record['fingerprint']) {
            return [...$record, 'outcome' => self::CHANGED];
        }

        foreach ($record['changes'] as $c => $change) {
            $current = Paths::get($data, $change['path']);

            // The fingerprint already says nothing changed; this is the same
            // promise checked where it matters, one string at a time.
            if (! is_string($current) || SweepStore::hash($current) !== $change['before_hash']) {
                return [...$record, 'outcome' => self::CHANGED];
            }

            $hits = self::selectedHits($change);

            if ($hits === []) {
                continue;
            }

            $after = Matcher::apply($current, $hits);
            $data = Paths::set($data, $change['path'], $after);
            $record['changes'][$c]['after_hash'] = SweepStore::hash($after);
        }

        try {
            $item->write($data, self::message($sweep), $user);
        } catch (Throwable $e) {
            foreach ($record['changes'] as $c => $change) {
                unset($record['changes'][$c]['after_hash']);
            }

            return [...$record, 'outcome' => self::FAILED, 'outcome_message' => $e->getMessage()];
        }

        return [...$record, 'outcome' => self::APPLIED, 'reference_after' => $item->reference()];
    }

    /**
     * Runs the writes with Statamic's Git subscriber off, and puts it back
     * whatever happens, so a failure halfway cannot leave a long-running
     * queue worker never committing again.
     *
     * @param  Closure(): bool  $writes
     */
    private function batch(Closure $writes): bool
    {
        if (! self::automaticGit()) {
            return $writes();
        }

        GitSubscriber::disable();

        try {
            return $writes();
        } finally {
            GitSubscriber::enable();
        }
    }

    private function commit(string $message, ?User $user): void
    {
        if (self::automaticGit()) {
            Git::as($user)->dispatchCommit($message);
        }
    }

    private static function automaticGit(): bool
    {
        return config('statamic.git.enabled') && config('statamic.git.automatic');
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private static function anySelected(array $record): bool
    {
        foreach ($record['changes'] ?? [] as $change) {
            if (self::selectedHits($change) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $change
     * @return list<Hit>
     */
    public static function selectedHits(array $change): array
    {
        $hits = [];

        foreach ($change['hits'] ?? [] as $hit) {
            if (($hit['selected'] ?? true) !== false) {
                $hits[] = new Hit((int) $hit['offset'], (int) $hit['length'], (string) $hit['text'], (string) $hit['replacement']);
            }
        }

        return $hits;
    }
}
