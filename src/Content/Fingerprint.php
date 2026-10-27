<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

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
