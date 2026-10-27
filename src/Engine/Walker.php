<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * Walks one item's data through its blueprint and lists every string the
 * query would change, plus the Bard matches it will not touch.
 *
 * Only fields the blueprint defines are read, so handles, IDs, set types,
 * Grid row ids and anything else that is structure rather than text are
 * never looked at. Within a field, only the strings its type holds as text
 * are searched.
 */
final class Walker
{
    /** Types whose value is a string of text. */
    private const TEXT_TYPES = ['text', 'textarea', 'markdown', 'video'];

    /** Values of a Link field that point at something rather than spell out a URL. */
    private const LINK_REFERENCES = ['entry::', 'asset::', '@child'];

    /** @var list<Change> */
    private array $changes = [];

    /** @var list<SplitMatch> */
    private array $splits = [];

    public function __construct(
        private readonly Matcher $matcher,
        private readonly Options $options = new Options,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the item's data, keyed by field handle
     * @param  list<Field>  $fields  the item's blueprint fields
     */
    public function walk(array $data, array $fields): Result
    {
        $this->changes = [];
        $this->splits = [];

        foreach ($fields as $field) {
            if ($this->options->handles !== [] && ! in_array($field->handle, $this->options->handles, true)) {
                continue;
            }

            $this->field($field, $data, [], []);
        }

        return new Result($this->changes, $this->splits);
    }

    /**
     * @param  array<array-key, mixed>  $data  the array that holds this field's value
     * @param  list<string|int>  $path  the path to $data
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function field(Field $field, array $data, array $path, array $label): void
    {
        if (! array_key_exists($field->handle, $data)) {
            return;
        }

        $this->value($field, $data[$field->handle], [...$path, $field->handle], [...$label, ['kind' => 'field', 'name' => $field->display]]);
    }

    /**
     * @param  list<Field>  $fields
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function fields(array $fields, mixed $data, array $path, array $label): void
    {
        if (! is_array($data)) {
            return;
        }

        foreach ($fields as $field) {
            $this->field($field, $data, $path, $label);
        }
    }

    /**
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function value(Field $field, mixed $value, array $path, array $label): void
    {
        $type = $field->type;

        if (in_array($type, self::TEXT_TYPES, true) || in_array($type, $this->options->extraTypes, true)) {
            $this->string($value, $path, $label);

            return;
        }

        match ($type) {
            'slug' => $this->options->slugs ? $this->string($value, $path, $label) : null,
            'code' => $this->options->code ? $this->code($value, $path, $label) : null,
            'link' => $this->link($value, $path, $label),
            'list' => $this->each($value, fn (mixed $item, int|string $key) => $this->string($item, [...$path, $key], $label)),
            'table' => $this->table($value, $path, $label),
            'array' => $this->keyValue($value, $path, $label),
            'group' => $this->fields($field->fields, $value, $path, $label),
            'grid' => $this->each($value, fn (mixed $row, int|string $key) => $this->fields(
                $field->fields, $row, [...$path, $key], [...$label, ['kind' => 'row', 'n' => (int) $key + 1]],
            )),
            'replicator' => $this->each($value, fn (mixed $set, int|string $key) => $this->set(
                $field, $set, [...$path, $key], $label, (int) $key + 1,
            )),
            'bard' => is_array($value) ? $this->bardNodes($field, $value, $path, $label, true) : null,
            default => null,
        };
    }

    /**
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function string(mixed $value, array $path, array $label): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $hits = $this->matcher->find($value);

        if ($hits !== []) {
            $this->changes[] = new Change($path, $label, $value, $hits);
        }
    }

    /**
     * A Code field holds a string, or `code` and `mode` when the mode can be
     * chosen. Only the code is text.
     *
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function code(mixed $value, array $path, array $label): void
    {
        is_array($value)
            ? $this->string($value['code'] ?? null, [...$path, 'code'], $label)
            : $this->string($value, $path, $label);
    }

    /**
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function link(mixed $value, array $path, array $label): void
    {
        if (! is_string($value)) {
            return;
        }

        foreach (self::LINK_REFERENCES as $reference) {
            if (str_starts_with($value, $reference)) {
                return;
            }
        }

        $this->string($value, $path, $label);
    }

    /**
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function table(mixed $rows, array $path, array $label): void
    {
        $this->each($rows, fn (mixed $row, int|string $r) => $this->each(
            is_array($row) ? ($row['cells'] ?? null) : null,
            fn (mixed $cell, int|string $c) => $this->string($cell, [...$path, $r, 'cells', $c], [...$label, ['kind' => 'row', 'n' => (int) $r + 1]]),
        ));
    }

    /**
     * An Array field is either keyed (key => value) or, with `expand`, a list
     * of `key` and `value` pairs. Only values are text; keys are handles.
     *
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function keyValue(mixed $value, array $path, array $label): void
    {
        $this->each($value, function (mixed $item, int|string $key) use ($path, $label): void {
            if (is_array($item) && array_key_exists('value', $item)) {
                $this->string($item['value'], [...$path, $key, 'value'], $label);

                return;
            }

            $this->string($item, [...$path, $key], $label);
        });
    }

    /**
     * A Replicator set, or the values of a Bard set: the fields its type
     * defines. A set whose type the blueprint no longer has is left alone.
     *
     * @param  array<array-key, mixed>|mixed  $values
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function set(Field $field, mixed $values, array $path, array $label, int $n): void
    {
        if (! is_array($values) || ! is_string($values['type'] ?? null) || ! isset($field->sets[$values['type']])) {
            return;
        }

        $set = $field->sets[$values['type']];

        $this->fields($set['fields'], $values, $path, [...$label, ['kind' => 'set', 'name' => $set['display'], 'n' => $n]]);
    }

    /**
     * ProseMirror nodes. Runs of text nodes side by side are searched as one
     * string, the way a person reads them, so a match that crosses from one
     * node into the next is seen and reported instead of missed. A match
     * inside one node is replaced in that node's `text` alone, which is what
     * keeps its marks.
     *
     * @param  array<array-key, mixed>  $nodes
     * @param  list<string|int>  $path  the path to $nodes
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function bardNodes(Field $field, array $nodes, array $path, array $label, bool $top): void
    {
        $run = [];

        foreach ($nodes as $index => $node) {
            if (is_array($node) && ($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null)) {
                $run[$index] = $node['text'];
                $this->linkMarks($node, [...$path, $index], $label);

                continue;
            }

            $this->textRun($run, $path, $label);
            $run = [];

            if (! is_array($node)) {
                continue;
            }

            $nodeLabel = $top ? [...$label, ['kind' => 'block', 'n' => (int) $index + 1]] : $label;

            match ($node['type'] ?? null) {
                'set' => $this->set($field, $node['attrs']['values'] ?? null, [...$path, $index, 'attrs', 'values'], $label, (int) $index + 1),
                'image' => $this->string($node['attrs']['alt'] ?? null, [...$path, $index, 'attrs', 'alt'], [...$nodeLabel, ['kind' => 'alt']]),
                default => null,
            };

            if (is_array($node['content'] ?? null)) {
                $this->bardNodes($field, $node['content'], [...$path, $index, 'content'], $nodeLabel, false);
            }
        }

        $this->textRun($run, $path, $label);
    }

    /**
     * @param  array<int|string, string>  $run  node index => text, in order
     * @param  list<string|int>  $path  the path to the nodes list
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function textRun(array $run, array $path, array $label): void
    {
        if ($run === []) {
            return;
        }

        $joined = implode('', $run);
        $hits = $this->matcher->find($joined);

        if ($hits === []) {
            return;
        }

        // Where each node starts in the joined string.
        $starts = [];
        $offset = 0;
        foreach ($run as $index => $text) {
            $starts[$index] = $offset;
            $offset += strlen($text);
        }

        $byNode = [];

        foreach ($hits as $hit) {
            $index = $this->nodeAt($starts, $hit->offset);
            $start = $starts[$index];

            if ($hit->end() > $start + strlen($run[$index])) {
                $this->splits[] = new SplitMatch($path, $label, $hit->text, self::context($joined, $hit));

                continue;
            }

            $byNode[$index][] = new Hit($hit->offset - $start, $hit->length, $hit->text, $hit->replacement);
        }

        foreach ($byNode as $index => $nodeHits) {
            $this->changes[] = new Change([...$path, $index, 'text'], $label, $run[$index], $nodeHits, $joined, $starts[$index]);
        }
    }

    /**
     * The node whose text holds the byte at $offset.
     *
     * @param  array<int|string, int>  $starts
     */
    private function nodeAt(array $starts, int $offset): int|string
    {
        $found = array_key_first($starts);

        foreach ($starts as $index => $start) {
            if ($start > $offset) {
                break;
            }
            $found = $index;
        }

        return $found;
    }

    /**
     * The `href` of each link mark on a text node.
     *
     * @param  array<array-key, mixed>  $node
     * @param  list<string|int>  $path  the path to the node
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    private function linkMarks(array $node, array $path, array $label): void
    {
        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $m => $mark) {
            if (is_array($mark) && ($mark['type'] ?? null) === 'link') {
                $this->string($mark['attrs']['href'] ?? null, [...$path, 'marks', $m, 'attrs', 'href'], [...$label, ['kind' => 'link']]);
            }
        }
    }

    /**
     * Calls $each for every item of $value, when it is an array.
     */
    private function each(mixed $value, callable $each): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            $each($item, $key);
        }
    }

    /** About 30 characters each side of a match, cut on whole characters. */
    private static function context(string $text, Hit $hit): string
    {
        $from = max(0, $hit->offset - 30);

        return mb_strcut($text, $from, $hit->end() - $from + 30, 'UTF-8');
    }
}
