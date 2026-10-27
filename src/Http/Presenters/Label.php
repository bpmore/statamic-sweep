<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Http\Presenters;

/**
 * The engine's label parts in words: "Content › Row 2 › Blocks › Set 1: Quote".
 */
final class Label
{
    /**
     * @param  list<array{kind: string, name?: string, n?: int}>  $parts
     */
    public static function words(array $parts): string
    {
        return implode(' › ', array_map(static fn (array $part): string => match ($part['kind'] ?? '') {
            'field' => (string) ($part['name'] ?? ''),
            'row' => __('sweep::messages.label_row', ['n' => $part['n'] ?? '']),
            'set' => __('sweep::messages.label_set', ['position' => $part['n'] ?? '', 'name' => $part['name'] ?? '']),
            'block' => __('sweep::messages.label_block', ['n' => $part['n'] ?? '']),
            'link' => __('sweep::messages.label_link'),
            'alt' => __('sweep::messages.label_alt'),
            default => '',
        }, $parts));
    }
}
