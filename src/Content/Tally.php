<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

/**
 * The counts a search reports: how much it looked at, what it found, and
 * what it left out and why.
 */
final class Tally
{
    public int $searched = 0;

    public int $matchedItems = 0;

    public int $matches = 0;

    public int $splits = 0;

    public int $skippedForPermission = 0;

    /** @var list<array{reference: string, title: string, message: string}> */
    public array $failures = [];

    /**
     * @return array{searched: int, matched_items: int, matches: int, splits: int, skipped_for_permission: int, failures: list<array{reference: string, title: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'searched' => $this->searched,
            'matched_items' => $this->matchedItems,
            'matches' => $this->matches,
            'splits' => $this->splits,
            'skipped_for_permission' => $this->skippedForPermission,
            'failures' => $this->failures,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $tally = new self;
        $tally->searched = (int) ($values['searched'] ?? 0);
        $tally->matchedItems = (int) ($values['matched_items'] ?? 0);
        $tally->matches = (int) ($values['matches'] ?? 0);
        $tally->splits = (int) ($values['splits'] ?? 0);
        $tally->skippedForPermission = (int) ($values['skipped_for_permission'] ?? 0);
        $tally->failures = array_values((array) ($values['failures'] ?? []));

        return $tally;
    }
}
