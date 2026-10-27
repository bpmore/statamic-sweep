<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Jobs;

use Bpmore\Sweep\Sweeps\Sweeps;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Undoes a sweep on the queue. One try, as for applying: whether to try
 * again is the person's call, made from the history.
 */
class UndoSweep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly string $sweepId,
        public readonly ?string $userId,
    ) {}

    public function handle(Sweeps $sweeps): void
    {
        $sweeps->runUndo($this->sweepId, $this->userId);
    }
}
