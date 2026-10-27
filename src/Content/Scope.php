<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Content;

use Bpmore\Sweep\Engine\InvalidQuery;
use Bpmore\Sweep\Engine\Options;

/**
 * Where a sweep looks.
 *
 * For each kind of content, null means all of it and an empty list means
 * none of it: `collections: ['blog']` with everything else empty is "blog
 * entries only". Sites, statuses and handles narrow whatever kinds are
 * included, and an empty list there means no narrowing.
 */
final class Scope
{
    public const KINDS = ['collections', 'taxonomies', 'globals', 'navs', 'containers'];

    public const STATUSES = ['published', 'draft', 'scheduled', 'expired'];

    /** The prefix each kind takes in a scope string, as in `collection:blog`. */
    private const PREFIXES = [
        'collection' => 'collections',
        'taxonomy' => 'taxonomies',
        'global' => 'globals',
        'nav' => 'navs',
        'assets' => 'containers',
    ];

    /**
     * @param  list<string>|null  $collections
     * @param  list<string>|null  $taxonomies
     * @param  list<string>|null  $globals
     * @param  list<string>|null  $navs
     * @param  list<string>|null  $containers
     * @param  list<string>  $sites
     * @param  list<string>  $statuses
     * @param  list<string>  $handles
     */
    public function __construct(
        public readonly ?array $collections = null,
        public readonly ?array $taxonomies = null,
        public readonly ?array $globals = null,
        public readonly ?array $navs = null,
        public readonly ?array $containers = null,
        public readonly array $sites = [],
        public readonly array $statuses = [],
        public readonly array $handles = [],
        public readonly bool $slugs = false,
        public readonly bool $code = false,
    ) {
        foreach ($statuses as $status) {
            if (! in_array($status, self::STATUSES, true)) {
                throw new InvalidQuery("There is no entry status called \"{$status}\". Use published, draft, scheduled or expired.");
            }
        }
    }

    /**
     * From the strings `sweep:run --scope=` takes, one per option:
     * `collection:blog`, `taxonomy:tags`, `global:footer`, `nav:main`,
     * `assets:images`, each with `*` for all of that kind, plus `site:en`,
     * `status:draft`, `field:title`. Naming any kind limits the sweep to the
     * kinds named. Naming none searches every kind.
     *
     * @param  list<string>  $strings
     */
    public static function fromStrings(array $strings, bool $slugs = false, bool $code = false): self
    {
        $kinds = array_fill_keys(self::KINDS, []);
        $named = false;
        $sites = $statuses = $handles = [];

        foreach ($strings as $string) {
            [$prefix, $value] = array_pad(explode(':', trim($string), 2), 2, '');

            if ($value === '') {
                throw new InvalidQuery("\"{$string}\" is not a scope. Write it as kind:handle, for example collection:blog.");
            }

            match (true) {
                $prefix === 'site' => $sites[] = $value,
                $prefix === 'status' => $statuses[] = $value,
                $prefix === 'field' => $handles[] = $value,
                isset(self::PREFIXES[$prefix]) => $kinds[self::PREFIXES[$prefix]] = $kinds[self::PREFIXES[$prefix]] === null || $value === '*'
                    ? null
                    : [...$kinds[self::PREFIXES[$prefix]], $value],
                default => throw new InvalidQuery("\"{$prefix}\" is not a kind of content. Use collection, taxonomy, global, nav, assets, site, status or field."),
            };

            $named = $named || isset(self::PREFIXES[$prefix]);
        }

        if (! $named) {
            $kinds = array_fill_keys(self::KINDS, null);
        }

        return new self(...$kinds, sites: $sites, statuses: $statuses, handles: $handles, slugs: $slugs, code: $code);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $list = static fn (mixed $value): array => array_values(array_map('strval', (array) $value));
        $kind = static fn (string $key): ?array => array_key_exists($key, $values) && $values[$key] !== null ? $list($values[$key]) : null;

        return new self(
            collections: $kind('collections'),
            taxonomies: $kind('taxonomies'),
            globals: $kind('globals'),
            navs: $kind('navs'),
            containers: $kind('containers'),
            sites: $list($values['sites'] ?? []),
            statuses: $list($values['statuses'] ?? []),
            handles: $list($values['handles'] ?? []),
            slugs: (bool) ($values['slugs'] ?? false),
            code: (bool) ($values['code'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'collections' => $this->collections,
            'taxonomies' => $this->taxonomies,
            'globals' => $this->globals,
            'navs' => $this->navs,
            'containers' => $this->containers,
            'sites' => $this->sites,
            'statuses' => $this->statuses,
            'handles' => $this->handles,
            'slugs' => $this->slugs,
            'code' => $this->code,
        ];
    }

    /** Whether a site is in scope. */
    public function coversSite(?string $site): bool
    {
        return $this->sites === [] || $site === null || in_array($site, $this->sites, true);
    }

    /**
     * @param  list<string>  $extraTypes
     */
    public function engineOptions(array $extraTypes = []): Options
    {
        return new Options(code: $this->code, slugs: $this->slugs, handles: $this->handles, extraTypes: $extraTypes);
    }
}
