<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;

/**
 * An entry, in its own site. A localization is its own item: its values are
 * its own, and the values it inherits belong to the entry it comes from.
 *
 * The slug is not in an entry's data; Statamic keeps it on the entry
 * itself. It is offered to the engine under `slug`, which is the handle the
 * blueprint's slug field has, and only when the search asked for slugs.
 */
final class EntryItem implements Item
{
    public function __construct(
        private readonly Entry $entry,
        private readonly bool $slugs = false,
    ) {}

    public static function referenceFor(string $id): string
    {
        return 'entry::'.$id;
    }

    public function entry(): Entry
    {
        return $this->entry;
    }

    public function reference(): string
    {
        return self::referenceFor((string) $this->entry->id());
    }

    public function kind(): string
    {
        return 'entry';
    }

    public function site(): ?string
    {
        return $this->entry->locale();
    }

    public function title(): string
    {
        return (string) ($this->entry->value('title') ?? $this->entry->slug() ?? $this->entry->id());
    }

    public function editUrl(): ?string
    {
        return $this->entry->editUrl();
    }

    public function fields(FieldTree $tree): array
    {
        return $tree->for($this->entry->blueprint());
    }

    public function data(): array
    {
        $data = $this->entry->data()->all();

        if ($this->slugs) {
            $data['slug'] = $this->entry->slug();
        }

        return $data;
    }

    public function mayEdit(User $user): bool
    {
        return (bool) $user->can('edit', $this->entry);
    }

    public function cannotWrite(): ?string
    {
        return $this->entry->revisionsEnabled() && $this->entry->hasWorkingCopy() ? 'working_copy' : null;
    }

    /**
     * Saved as the person applying the sweep, and, where the collection keeps
     * revisions, recorded as a revision with the sweep's message, the way the
     * control panel records a save.
     */
    public function write(array $data, string $message, ?User $user): void
    {
        $current = $this->data();

        foreach ($data as $handle => $value) {
            if (array_key_exists($handle, $current) && $current[$handle] === $value) {
                continue;
            }

            $handle === 'slug' && $this->slugs
                ? $this->entry->slug($value)
                : $this->entry->set($handle, $value);
        }

        $this->entry->updateLastModified($user)->save();

        if ($this->entry->revisionsEnabled()) {
            $this->entry->makeRevision()->user($user)->message($message)->save();
        }
    }
}
