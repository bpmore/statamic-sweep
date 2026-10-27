<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Jobs;

use Bpmore\Sweep\Sweeps\Sweeps;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Applies a sweep on the queue. One try: retrying would run a half-applied
 * batch again, and the items it already wrote would then be "changed since
 * preview" and skipped anyway, so a retry gains nothing and costs a muddled
 * history.
 */
class ApplySweep implements ShouldQueue
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
        $sweeps->runApply($this->sweepId, $this->userId);
    }
}
