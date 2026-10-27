<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * One match in one string: where it is, what it says, and what it becomes.
 *
 * Offsets and lengths are in bytes of the UTF-8 string, which is what PCRE
 * reports and what substr_replace needs.
 */
final class Hit
{
    public function __construct(
        public readonly int $offset,
        public readonly int $length,
        public readonly string $text,
        public readonly string $replacement,
    ) {}

    public function end(): int
    {
        return $this->offset + $this->length;
    }
}
