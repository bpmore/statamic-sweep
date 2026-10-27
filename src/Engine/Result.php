<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * What a search found in one item.
 */
final class Result
{
    /**
     * @param  list<Change>  $changes
     * @param  list<SplitMatch>  $splits
     */
    public function __construct(
        public readonly array $changes,
        public readonly array $splits,
    ) {}

    public function isEmpty(): bool
    {
        return $this->changes === [] && $this->splits === [];
    }

    public function matchCount(): int
    {
        return array_sum(array_map(static fn (Change $change): int => count($change->hits), $this->changes));
    }
}
