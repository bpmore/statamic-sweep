<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * A match that crosses from one Bard text node into the next, for example a
 * phrase half in bold, or half inside a link. Replacing it would mean
 * choosing which node's marks the new text gets, so Sweep never does. It is
 * listed for a person to edit by hand.
 *
 * `path` is the inline container (the paragraph or heading) the match is in.
 */
final class SplitMatch
{
    /**
     * @param  list<string|int>  $path
     * @param  list<array{kind: string, name?: string, n?: int}>  $label
     */
    public function __construct(
        public readonly array $path,
        public readonly array $label,
        public readonly string $text,
        public readonly string $context,
    ) {}
}
