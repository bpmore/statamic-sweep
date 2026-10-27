<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Bpmore\Sweep\Engine\Field as EngineField;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;

/**
 * A blueprint, reduced to the field tree the engine walks.
 *
 * Read through Statamic's own Fields, so imported fieldsets, prefixes and
 * the old and new Replicator set formats all come out the way the publish
 * form sees them. Built once per blueprint per search, since a site with
 * 10,000 entries usually has a handful of blueprints.
 */
final class FieldTree
{
    /** Deeper than any real blueprint; a guard against a set importing itself. */
    private const MAX_DEPTH = 25;

    /** @var array<string, list<EngineField>> */
    private array $built = [];

    /**
     * @return list<EngineField>
     */
    public function for(Blueprint $blueprint): array
    {
        $key = $blueprint->namespace().'::'.$blueprint->handle();

        return $this->built[$key] ??= $this->fields($blueprint->fields(), 0);
    }

    /**
     * @return list<EngineField>
     */
    private function fields(Fields $fields, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        return $fields->all()
            ->map(fn (Field $field): EngineField => $this->field($field, $depth))
            ->values()
            ->all();
    }

    private function field(Field $field, int $depth): EngineField
    {
        $type = (string) $field->type();
        $fieldtype = $field->fieldtype();
        $nested = [];
        $sets = [];

        if (in_array($type, ['grid', 'group'], true)) {
            $nested = $this->fields($fieldtype->fields(), $depth + 1);
        }

        if (in_array($type, ['replicator', 'bard'], true)) {
            foreach ($fieldtype->flattenedSetsConfig() as $handle => $config) {
                $sets[(string) $handle] = [
                    'display' => (string) __($config['display'] ?? $handle),
                    'fields' => $this->fields($fieldtype->fields($handle), $depth + 1),
                ];
            }
        }

        return new EngineField(
            handle: (string) $field->handle(),
            type: $type,
            display: (string) $field->display(),
            fields: $nested,
            sets: $sets,
        );
    }
}
