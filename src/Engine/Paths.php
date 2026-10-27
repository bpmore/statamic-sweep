<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * Reading and writing one value deep inside nested arrays, by a list of keys.
 */
final class Paths
{
    /** Marks a path that leads nowhere, which null cannot, since null is a value. */
    public const MISSING = "\0sweep:missing";

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string|int>  $path
     */
    public static function get(array $data, array $path): mixed
    {
        foreach ($path as $key) {
            if (! is_array($data) || ! array_key_exists($key, $data)) {
                return self::MISSING;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /**
     * The data with the value at $path replaced. Only a path that already
     * exists can be set: Sweep changes strings it found, it never adds keys.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string|int>  $path
     * @return array<array-key, mixed>
     */
    public static function set(array $data, array $path, mixed $value): array
    {
        if ($path === []) {
            return $data;
        }

        $key = array_shift($path);

        if (! array_key_exists($key, $data)) {
            return $data;
        }

        $data[$key] = $path === [] ? $value : (is_array($data[$key]) ? self::set($data[$key], $path, $value) : $data[$key]);

        return $data;
    }
}
