<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Sweeps;

use Bpmore\Sweep\Content\Item;
use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Content\Search;
use Bpmore\Sweep\Content\Tally;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Engine\Result;
use Bpmore\Sweep\Jobs\ApplySweep;
use Bpmore\Sweep\Jobs\SearchSweep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\User as Users;
use Throwable;

/**
 * Starting sweeps and running their searches. The control panel and
 * `sweep:run` both come through here, so a sweep is the same thing whoever
 * started it.
 */
final class Sweeps
{
    /** How often, in items searched, a running search writes its progress. */
    private const PROGRESS_EVERY = 50;

    public function __construct(
        private readonly SweepStore $store,
        private readonly Search $search,
        private readonly Applier $applier,
    ) {}

    /**
     * Records a new sweep and queues its search. With `$now`, the search
     * runs before this returns, which is what Artisan wants.
     */
    public function start(Query $query, Scope $scope, ?User $user, bool $now = false): Sweep
    {
        $sweep = new Sweep(
            id: $this->store->newId(),
            status: Status::Searching,
            query: $query,
            scope: $scope,
            createdBy: self::person($user),
            createdAt: Carbon::now()->toIso8601String(),
            progress: ['phase' => 'search', 'done' => 0],
        );

        $this->store->save($sweep);

        $now ? SearchSweep::dispatchSync($sweep->id) : SearchSweep::dispatch($sweep->id);

        return $this->store->find($sweep->id) ?? $sweep;
    }

    /**
     * The search itself: items found are written a chunk at a time as the
     * search goes, so the control panel can show the first results while
     * the rest are still being looked for.
     */
    public function search(string $id): void
    {
        $sweep = $this->store->find($id);

        if ($sweep === null || $sweep->status !== Status::Searching) {
            return;
        }

        $user = $sweep->createdBy ? Users::find($sweep->createdBy['id']) : null;

        // A sweep started by a person is searched as that person. If they
        // have since been deleted, nothing is searched, rather than
        // everything.
        if ($sweep->createdBy !== null && $user === null) {
            $this->fail($sweep, __('sweep::messages.error_starter_gone'));

            return;
        }

        $buffer = [];

        try {
            $tally = $this->search->run(
                $sweep->query,
                $sweep->scope,
                $user,
                function (Item $item, Result $result, string $fingerprint) use (&$buffer, $sweep): void {
                    $buffer[] = SweepStore::record($item, $result, $fingerprint);

                    if (count($buffer) === SweepStore::CHUNK) {
                        $this->flush($sweep, $buffer);
                        $buffer = [];
                    }
                },
                function (Tally $tally) use ($sweep): void {
                    if (($tally->searched + $tally->skippedForPermission) % self::PROGRESS_EVERY === 0) {
                        $sweep->tally = $tally;
                        $sweep->progress = ['phase' => 'search', 'done' => $tally->searched + $tally->skippedForPermission];
                        $this->store->save($sweep);
                    }
                },
            );
        } catch (Throwable $e) {
            $this->fail($sweep, $e->getMessage());

            throw $e;
        }

        if ($buffer !== []) {
            $this->flush($sweep, $buffer);
        }

        $sweep->tally = $tally;
        $sweep->status = Status::Previewed;
        $sweep->progress = ['phase' => 'search', 'done' => $tally->searched + $tally->skippedForPermission];
        $this->store->save($sweep);
    }

    /**
     * Queues a previewed sweep to be applied, as this person. Returns false,
     * changing nothing, when the sweep is not a preview waiting to be
     * applied: already applied, still searching, or being applied by
     * somebody else this moment.
     */
    public function apply(Sweep $sweep, ?User $user, bool $now = false): bool
    {
        // Two people pressing Apply at once: the lock lets one through, and
        // the other then finds the sweep already applying.
        $fresh = Cache::lock('sweep-'.$sweep->id, 10)->get(function () use ($sweep): ?Sweep {
            $fresh = $this->store->find($sweep->id);

            if ($fresh === null || $fresh->status !== Status::Previewed) {
                return null;
            }

            $fresh->status = Status::Applying;
            $fresh->progress = ['phase' => 'apply', 'done' => 0, 'total' => $fresh->items];
            $this->store->save($fresh);

            return $fresh;
        });

        if (! $fresh instanceof Sweep) {
            return false;
        }

        $job = [$fresh->id, $user ? (string) $user->id() : null];
        $now ? ApplySweep::dispatchSync(...$job) : ApplySweep::dispatch(...$job);

        return true;
    }

    /** The queued half of apply(). */
    public function runApply(string $id, ?string $userId): void
    {
        $sweep = $this->store->find($id);

        if ($sweep === null || $sweep->status !== Status::Applying) {
            return;
        }

        $user = $userId !== null ? Users::find($userId) : null;

        if ($userId !== null && $user === null) {
            $this->fail($sweep, __('sweep::messages.error_applier_gone'));

            return;
        }

        try {
            $this->applier->apply($id, $user);
        } catch (Throwable $e) {
            $this->fail($this->store->find($id) ?? $sweep, $e->getMessage());

            throw $e;
        }
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public static function person(?User $user): ?array
    {
        return $user ? ['id' => (string) $user->id(), 'name' => (string) ($user->name() ?: $user->email())] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function flush(Sweep $sweep, array $records): void
    {
        $sweep->chunks++;
        $this->store->writeChunk($sweep->id, $sweep->chunks, $records);
        $sweep->items += count($records);
        $this->store->save($sweep);
    }

    private function fail(Sweep $sweep, string $message): void
    {
        $sweep->status = Status::Failed;
        $sweep->error = $message;
        $this->store->save($sweep);
    }
}
