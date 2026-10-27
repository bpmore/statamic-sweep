<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Bpmore\Sweep\Engine\Field;

/**
 * A short hash of an item's data, taken when it is previewed and again just
 * before it is written. If the two differ, somebody edited the item in
 * between, and it is skipped rather than written over.
 *
 * Keys are sorted at every level that is a map, so the same values in a
 * different key order (which a YAML round trip or a database driver can
 * produce) are the same fingerprint. Lists keep their order, because order
 * is content there.
 */
final class Fingerprint
{
    /**
     * The fingerprint of the values in an item's blueprint fields only.
     *
     * Anything else in an item's data is bookkeeping the storage adds, and
     * not always the same way: on the Eloquent driver an entry loaded by a
     * query carries `updated_at` and the same entry loaded by id does not.
     * Fingerprinting that would flag every such entry as edited. An editor's
     * change is always to a blueprint field, so this still catches it.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<Field>  $fields
     */
    public static function ofFields(array $data, array $fields): string
    {
        $handles = array_map(static fn ($field): string => $field->handle, $fields);

        return self::of(array_intersect_key($data, array_flip($handles)));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function of(array $data): string
    {
        return hash('sha256', (string) json_encode(self::normalise($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function normalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::normalise(...), $value);
    }
}
