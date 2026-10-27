<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Sweeps;

/**
 * Where a sweep is in its life. `searching`, `applying` and `undoing` are
 * the moments a queued job is working on it; the rest are where it rests.
 */
enum Status: string
{
    case Searching = 'searching';
    case Previewed = 'previewed';
    case Applying = 'applying';
    case Applied = 'applied';
    case Undoing = 'undoing';
    case Undone = 'undone';
    case PartiallyUndone = 'partially_undone';
    case Failed = 'failed';

    public function isWorking(): bool
    {
        return in_array($this, [self::Searching, self::Applying, self::Undoing], true);
    }
}
