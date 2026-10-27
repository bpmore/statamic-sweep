<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Taxonomies\Term;

/**
 * A taxonomy term in one site.
 *
 * A term's identity is its slug in the taxonomy's first site, so a sweep that
 * changes that slug changes the term's reference. Apply records the
 * reference after the write for undo to use.
 */
final class TermItem implements Item
{
    /**
     * @param  Term  $term  a LocalizedTerm: the term in one site
     */
    public function __construct(
        private readonly Term $term,
        private readonly bool $slugs = false,
    ) {}

    public static function referenceFor(string $id, string $site): string
    {
        return 'term::'.$id.'::'.$site;
    }

    public function reference(): string
    {
        return self::referenceFor((string) $this->term->id(), (string) $this->term->locale());
    }

    public function kind(): string
    {
        return 'term';
    }

    public function site(): ?string
    {
        return $this->term->locale();
    }

    public function title(): string
    {
        return (string) ($this->term->title() ?? $this->term->slug());
    }

    public function editUrl(): ?string
    {
        return $this->term->editUrl();
    }

    public function fields(FieldTree $tree): array
    {
        return $tree->for($this->term->blueprint());
    }

    public function data(): array
    {
        $data = $this->term->data()->all();

        if ($this->slugs) {
            $data['slug'] = $this->term->slug();
        } else {
            // A localization keeps its own slug in its data. It is still a
            // slug, and stays out of the search unless slugs are asked for.
            unset($data['slug']);
        }

        return $data;
    }

    public function mayEdit(User $user): bool
    {
        return (bool) $user->can('edit', $this->term);
    }

    public function write(array $data, string $message, ?User $user): void
    {
        $current = $this->data();

        foreach ($data as $handle => $value) {
            if (array_key_exists($handle, $current) && $current[$handle] === $value) {
                continue;
            }

            $handle === 'slug'
                ? ($this->slugs ? $this->term->slug($value) : null)
                : $this->term->set($handle, $value);
        }

        $this->term->save();
    }
}
