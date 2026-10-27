<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * One string in an item's data that a sweep would change.
 *
 * `path` is the exact place in the item's data, as keys from the top:
 * `['content', 2, 'content', 0, 'text']` is the first text node of the third
 * block of a Bard field. `label` is the same place as parts a person can
 * read once the control panel words them: the field's display
 * name, "row 2", "set 3: Quote", "block 4", "link".
 *
 * `before` is the whole string, kept so that a person can always see what was
 * there, and so that undo has the exact bytes to put back. The after value is
 * always `before` with `hits` applied, so it is worked out, not stored.
 *
 * Every change is a string replaced by a string. Applying and undoing never
 * touch anything else, which is what keeps Bard marks, set data and sibling
 * fields exactly as they were.
 */
final class Change
{
    /**
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     * @param  list<Hit>  $hits
     * @param  string|null  $surroundings  the text a person reads around
     *                                     `before`, when that is more than
     *                                     `before` itself: a Bard text node's
     *                                     whole paragraph, for the diff
     * @param  int  $surroundingsOffset  where `before` starts in it
     */
    public function __construct(
        public readonly array $path,
        public readonly array $label,
        public readonly string $before,
        public readonly array $hits,
        public readonly ?string $surroundings = null,
        public readonly int $surroundingsOffset = 0,
    ) {}

    /** The string with every hit replaced. */
    public function after(): string
    {
        return Matcher::apply($this->before, $this->hits);
    }

    /**
     * The string with only some hits replaced, by their index in `hits`,
     * for when a person deselects single matches in the preview.
     *
     * @param  list<int>  $indexes
     */
    public function afterWith(array $indexes): string
    {
        $chosen = array_values(array_intersect_key($this->hits, array_flip($indexes)));

        return Matcher::apply($this->before, $chosen);
    }

    /** Dotted, for display and for keys: `content.2.content.0.text`. */
    public function key(): string
    {
        return implode('.', $this->path);
    }
}
