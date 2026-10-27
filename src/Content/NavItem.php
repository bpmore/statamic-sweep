<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Bpmore\Sweep\Engine\Field;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Structures\NavTree;

/**
 * A navigation tree in one site: each branch's title and URL, and the
 * values of the nav's blueprint fields, which Statamic keeps under a
 * branch's `data`.
 *
 * The tree is offered to the engine as a Grid called `tree`, each row a
 * branch whose `children` is another Grid, so the engine walks it with the
 * code it already has. Branch ids and entry references are not fields of
 * that Grid, so they are never searched.
 */
final class NavItem implements Item
{
    /** Deeper than a nav with no limit is likely to go; branches below it are not searched. */
    private const DEFAULT_DEPTH = 10;

    public function __construct(private readonly NavTree $tree) {}

    public static function referenceFor(string $handle, string $site): string
    {
        return 'nav::'.$handle.'::'.$site;
    }

    public function reference(): string
    {
        return self::referenceFor((string) $this->tree->handle(), (string) $this->tree->locale());
    }

    public function kind(): string
    {
        return 'nav';
    }

    public function site(): ?string
    {
        return $this->tree->locale();
    }

    public function title(): string
    {
        return (string) $this->tree->structure()->title();
    }

    public function editUrl(): ?string
    {
        return $this->tree->structure()->showUrl(['site' => $this->tree->locale()]);
    }

    public function fields(FieldTree $tree): array
    {
        $nav = $this->tree->structure();
        $custom = array_values(array_filter(
            $tree->for($nav->blueprint()),
            static fn (Field $field): bool => ! in_array($field->handle, ['title', 'url'], true),
        ));

        return [$this->branches((string) $nav->title(), $custom, (int) ($nav->maxDepth() ?: self::DEFAULT_DEPTH))];
    }

    public function data(): array
    {
        return ['tree' => $this->tree->tree()];
    }

    public function mayEdit(User $user): bool
    {
        return (bool) $user->can('edit', $this->tree);
    }

    public function cannotWrite(): ?string
    {
        return null;
    }

    public function write(array $data, string $message, ?User $user): void
    {
        if (($data['tree'] ?? null) === $this->tree->tree()) {
            return;
        }

        $this->tree->tree($data['tree'])->save();
    }

    /**
     * @param  list<Field>  $custom
     */
    private function branches(string $display, array $custom, int $depth): Field
    {
        $fields = [
            new Field('title', 'text', __('Title')),
            new Field('url', 'text', __('URL')),
        ];

        if ($custom !== []) {
            $fields[] = new Field('data', 'group', __('Data'), $custom);
        }

        if ($depth > 1) {
            $fields[] = $this->branches($display, $custom, $depth - 1)->renamed('children');
        }

        return new Field('tree', 'grid', $display, $fields);
    }
}
