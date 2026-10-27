<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Term;

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
        yield from $this->terms($scope);
        yield from $this->globals($scope);
        yield from $this->navs($scope);
    }

    public function find(string $reference): ?Item
    {
        [$kind, $rest] = array_pad(explode('::', $reference, 2), 2, '');

        return match ($kind) {
            'entry' => ($entry = Entry::find($rest)) ? new EntryItem($entry) : null,
            'term' => $this->findTerm($rest),
            'global' => $this->findGlobal($rest),
            'nav' => $this->findNav($rest),
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

        if (! $scope->slugs) {
            return $item;
        }

        return match (true) {
            $item instanceof EntryItem => new EntryItem($item->entry(), true),
            $item instanceof TermItem => $this->findTerm(substr($reference, strlen('term::')), true),
            default => $item,
        };
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

    /**
     * @return iterable<Item>
     */
    private function terms(Scope $scope): iterable
    {
        if ($scope->taxonomies === []) {
            return;
        }

        $query = Term::query();

        if ($scope->taxonomies !== null) {
            $query->whereIn('taxonomy', $scope->taxonomies);
        }

        if ($scope->sites !== []) {
            $query->whereIn('site', $scope->sites);
        }

        foreach ($query->orderBy('id')->lazy(self::CHUNK) as $term) {
            yield new TermItem($term, $scope->slugs);
        }
    }

    /**
     * @return iterable<Item>
     */
    private function globals(Scope $scope): iterable
    {
        if ($scope->globals === []) {
            return;
        }

        foreach (GlobalSet::all()->sortBy(fn ($set) => $set->handle()) as $set) {
            if ($scope->globals !== null && ! in_array($set->handle(), $scope->globals, true)) {
                continue;
            }

            foreach ($set->sites() as $site) {
                if (! $scope->coversSite($site) || ! ($variables = $set->in($site))) {
                    continue;
                }

                yield new GlobalItem($variables);
            }
        }
    }

    /**
     * @return iterable<Item>
     */
    private function navs(Scope $scope): iterable
    {
        if ($scope->navs === []) {
            return;
        }

        foreach (Nav::all()->sortBy(fn ($nav) => $nav->handle()) as $nav) {
            if ($scope->navs !== null && ! in_array($nav->handle(), $scope->navs, true)) {
                continue;
            }

            foreach ($nav->trees()->sortKeys() as $site => $tree) {
                if ($scope->coversSite((string) $site)) {
                    yield new NavItem($tree);
                }
            }
        }
    }

    /** `<handle>::<site>` */
    private function findNav(string $rest): ?NavItem
    {
        [$handle, $site] = array_pad(explode('::', $rest, 2), 2, '');
        $tree = Nav::findByHandle($handle)?->in($site);

        return $tree ? new NavItem($tree) : null;
    }

    /** `<taxonomy>::<slug>::<site>` */
    private function findTerm(string $rest, bool $slugs = false): ?TermItem
    {
        $parts = explode('::', $rest);
        $site = array_pop($parts);
        $term = Term::find(implode('::', $parts))?->in($site);

        return $term ? new TermItem($term, $slugs) : null;
    }

    /** `<handle>::<site>` */
    private function findGlobal(string $rest): ?GlobalItem
    {
        [$handle, $site] = array_pad(explode('::', $rest, 2), 2, '');
        $variables = GlobalSet::findByHandle($handle)?->in($site);

        return $variables ? new GlobalItem($variables) : null;
    }
}
