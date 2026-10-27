<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Commands;

use Bpmore\Sweep\Sweeps\Sweeps;
use Illuminate\Console\Command;

/**
 * Runs daily. Removes the undo data of sweeps past the undo period, and
 * keeps their place in the history.
 */
class PruneSweeps extends Command
{
    protected $signature = 'sweep:prune';

    protected $description = 'Remove undo data for sweeps older than the undo period';

    public function handle(Sweeps $sweeps): int
    {
        $count = $sweeps->prune();
        $days = (int) config('sweep.undo_days', 30);

        $this->components->info(__('sweep::messages.cli_pruned', [
            'sweeps' => trans_choice('sweep::messages.n_sweeps', $count, ['count' => $count]),
            'days' => trans_choice('sweep::messages.n_days', $days, ['count' => $days]),
        ]));

        return self::SUCCESS;
    }
}
