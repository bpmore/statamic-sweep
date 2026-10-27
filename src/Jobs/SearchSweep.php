<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Jobs;

use Bpmore\Sweep\Sweeps\Sweeps;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Searches a sweep on the queue. One try: a search that fails halfway is
 * marked failed, and starting it again is the person's call.
 */
class SearchSweep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** A search of a very large site can take a while. */
    public int $timeout = 3600;

    public function __construct(public readonly string $sweepId) {}

    public function handle(Sweeps $sweeps): void
    {
        $sweeps->search($this->sweepId);
    }
}
