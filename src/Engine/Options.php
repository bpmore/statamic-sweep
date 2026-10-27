<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * Which fields a search may look in, beyond the defaults.
 *
 *   code       Code fields are off by default: a search for a word should
 *              not quietly rewrite somebody's snippet.
 *   slugs      Slug fields are off by default: changing one changes a URL.
 *   handles    Only these top-level field handles, when not empty.
 *   extraTypes Third-party fieldtypes that store a plain string, named in
 *              config, searched like a text field.
 */
final class Options
{
    /**
     * @param  list<string>  $handles
     * @param  list<string>  $extraTypes
     */
    public function __construct(
        public readonly bool $code = false,
        public readonly bool $slugs = false,
        public readonly array $handles = [],
        public readonly array $extraTypes = [],
    ) {}
}
