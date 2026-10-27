<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * A field from a blueprint, reduced to what a search needs: its handle, its
 * type, a name to show, and the fields nested inside it.
 *
 *   fields  Grid and Group: the fields of each row, or of the group
 *   sets    Replicator and Bard: set handle => [display, fields]
 *
 * Built from plain arrays so the engine never needs Statamic to read one.
 */
final class Field
{
    /**
     * @param  list<Field>  $fields
     * @param  array<string, array{display: string, fields: list<Field>}>  $sets
     */
    public function __construct(
        public readonly string $handle,
        public readonly string $type,
        public readonly string $display = '',
        public readonly array $fields = [],
        public readonly array $sets = [],
    ) {}

    /** The same field under another handle. */
    public function renamed(string $handle): self
    {
        return new self($handle, $this->type, $this->display, $this->fields, $this->sets);
    }

    /**
     * @param  array{handle: string, type: string, display?: string, fields?: list<array<string, mixed>>, sets?: array<string, array{display?: string, fields?: list<array<string, mixed>>}>}  $definition
     */
    public static function fromArray(array $definition): self
    {
        $sets = [];

        foreach ($definition['sets'] ?? [] as $handle => $set) {
            $sets[(string) $handle] = [
                'display' => (string) ($set['display'] ?? $handle),
                'fields' => array_map(self::fromArray(...), array_values($set['fields'] ?? [])),
            ];
        }

        return new self(
            handle: (string) $definition['handle'],
            type: (string) $definition['type'],
            display: (string) ($definition['display'] ?? $definition['handle']),
            fields: array_map(self::fromArray(...), array_values($definition['fields'] ?? [])),
            sets: $sets,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return list<Field>
     */
    public static function listFromArray(array $definitions): array
    {
        return array_map(self::fromArray(...), array_values($definitions));
    }
}
