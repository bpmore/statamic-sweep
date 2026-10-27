<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Engine\Result;
use Bpmore\Sweep\Engine\Walker;
use RuntimeException;
use Statamic\Contracts\Auth\User;

/**
 * Runs a query over every item in a scope and hands each item that matched
 * to a callback, as it is found, so results can be written a chunk at a time
 * instead of held in memory.
 *
 * With a person, only items they may edit are searched. The rest are counted
 * and never read, so nothing about them can reach the results. Without one
 * (an Artisan command run by whoever deploys the site) everything in scope
 * is searched.
 */
final class Search
{
    /**
     * @param  list<string>  $extraTypes  fieldtypes config says hold plain text
     */
    public function __construct(
        private readonly Items $items,
        private readonly array $extraTypes = [],
    ) {}

    /**
     * @param  callable(Item, Result, string): void  $found  the item, what matched, and its fingerprint
     */
    public function run(Query $query, Scope $scope, ?User $user, callable $found): Tally
    {
        $walker = new Walker(new Matcher($query), $scope->engineOptions($this->extraTypes));
        $tree = new FieldTree;
        $tally = new Tally;

        foreach ($this->items->in($scope) as $item) {
            if ($user !== null && ! $item->mayEdit($user)) {
                $tally->skippedForPermission++;

                continue;
            }

            $tally->searched++;
            $data = $item->data();

            try {
                $result = $walker->walk($data, $item->fields($tree));
            } catch (RuntimeException $e) {
                $tally->failures[] = ['reference' => $item->reference(), 'title' => $item->title(), 'message' => $e->getMessage()];

                continue;
            }

            if ($result->isEmpty()) {
                continue;
            }

            $tally->matchedItems++;
            $tally->matches += $result->matchCount();
            $tally->splits += count($result->splits);

            $found($item, $result, Fingerprint::of($data));
        }

        return $tally;
    }
}
