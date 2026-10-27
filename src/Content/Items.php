<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

/**
 * Every item in a scope, read a chunk at a time so a large site is never
 * loaded into memory at once, and any one item found again by reference.
 */
final class Items
{
    private const CHUNK = 200;

    /**
     * @return iterable<Item>
     */
    public function in(Scope $scope): iterable
    {
        yield from $this->entries($scope);
    }

    public function find(string $reference): ?Item
    {
        [$kind, $rest] = array_pad(explode('::', $reference, 2), 2, '');

        return match ($kind) {
            'entry' => ($entry = Entry::find($rest)) ? new EntryItem($entry) : null,
            default => null,
        };
    }

    /**
     * Same as find(), with slugs offered to the engine when the sweep that
     * names the item searched them.
     */
    public function findFor(string $reference, Scope $scope): ?Item
    {
        $item = $this->find($reference);

        return $item instanceof EntryItem ? new EntryItem($item->entry(), $scope->slugs) : $item;
    }

    /**
     * @return iterable<Item>
     */
    private function entries(Scope $scope): iterable
    {
        if ($scope->collections === []) {
            return;
        }

        $query = Entry::query();

        if ($scope->collections !== null) {
            $query->whereIn('collection', $scope->collections);
        }

        if ($scope->sites !== []) {
            $query->whereIn('site', $scope->sites);
        }

        // Status is read from each entry rather than queried: an entry's
        // status depends on its collection's date behaviour, and reading it
        // the way the control panel does means both agree.
        foreach ($query->orderBy('id')->lazy(self::CHUNK) as $entry) {
            /** @var EntryContract $entry */
            if ($scope->statuses !== [] && ! in_array($entry->status(), $scope->statuses, true)) {
                continue;
            }

            yield new EntryItem($entry, $scope->slugs);
        }
    }
}
