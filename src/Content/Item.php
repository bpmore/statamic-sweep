<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Bpmore\Sweep\Engine\Field;
use Statamic\Contracts\Auth\User;

/**
 * One thing a sweep can change: an entry, a term in one site, a global set
 * in one site, a navigation tree in one site, or an asset.
 *
 * Each kind is read and written through Statamic's own repositories, never
 * its files, so the same code works on flat files and on the Eloquent
 * driver.
 */
interface Item
{
    /**
     * A string that finds this item again later, for apply and undo:
     * `entry::<id>`, `term::<taxonomy>::<slug>::<site>`, and so on.
     */
    public function reference(): string;

    /** entry, term, global, nav or asset. */
    public function kind(): string;

    /** The site handle, or null for something that is not per site. */
    public function site(): ?string;

    /** What to call it in the results. */
    public function title(): string;

    /** Where a person edits it, or null when there is no such screen. */
    public function editUrl(): ?string;

    /**
     * The fields the engine walks.
     *
     * @return list<Field>
     */
    public function fields(FieldTree $tree): array;

    /**
     * The values the engine walks, keyed by the field handles in fields().
     * Only what this item owns: a localization's values inherited from its
     * origin are the origin's to change.
     *
     * @return array<string, mixed>
     */
    public function data(): array;

    /** Whether this person may edit it in the control panel. */
    public function mayEdit(User $user): bool;

    /**
     * Saves new values through Statamic, so validation listeners, events,
     * search indexing and static cache invalidation all run. `$data` is
     * data() with some values changed; only the changed ones are written.
     *
     * @param  array<string, mixed>  $data
     */
    public function write(array $data, string $message, ?User $user): void;
}
