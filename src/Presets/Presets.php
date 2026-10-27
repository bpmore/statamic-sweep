<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Presets;

use Bpmore\Sweep\Engine\InvalidQuery;
use Bpmore\Sweep\Engine\Query;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Saved searches, as YAML.
 *
 * The three that ship with Sweep live in the addon and cannot be changed or
 * deleted. A site's own live in resources/sweep/presets, where editors'
 * work belongs: in the site's Git history, not in storage. A site preset with
 * the same handle as a built-in one takes its place.
 *
 * A preset is a search, not a scope: where to look is chosen each time.
 */
final class Presets
{
    /** The keys a preset file may hold. */
    private const KEYS = ['title', 'description', 'find', 'replace', 'mode', 'case_sensitive', 'preserve_case'];

    public function __construct(
        private readonly string $sitePath,
        private readonly string $builtInPath = __DIR__.'/../../resources/presets',
        private readonly Filesystem $files = new Filesystem,
    ) {}

    /**
     * Every preset, by title.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $presets = [];

        foreach ([[$this->builtInPath, true], [$this->sitePath, false]] as [$path, $builtIn]) {
            foreach ($this->files->isDirectory($path) ? $this->files->glob($path.'/*.yaml') : [] as $file) {
                try {
                    $preset = self::parse((string) $this->files->get($file));
                } catch (InvalidQuery) {
                    continue;
                }

                $handle = basename($file, '.yaml');
                $presets[$handle] = [...$preset, 'handle' => $handle, 'built_in' => $builtIn];
            }
        }

        usort($presets, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        return array_values($presets);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $handle): ?array
    {
        foreach ($this->all() as $preset) {
            if ($preset['handle'] === $handle) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Saves a site preset and returns its handle, made from its title.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws InvalidQuery when the search in it would not run
     */
    public function save(array $values): string
    {
        $preset = self::validate($values);
        $handle = Str::slug($preset['title']) ?: 'preset';

        $this->files->ensureDirectoryExists($this->sitePath);
        $this->files->put($this->sitePath.'/'.$handle.'.yaml', self::dump($preset));

        return $handle;
    }

    /**
     * Deletes a site preset. Built-in presets cannot be deleted.
     */
    public function delete(string $handle): bool
    {
        if (preg_match('/^[a-z0-9-]+$/', $handle) !== 1) {
            return false;
        }

        $file = $this->sitePath.'/'.$handle.'.yaml';

        return $this->files->exists($file) && $this->files->delete($file);
    }

    /**
     * A preset as the YAML that shares it.
     *
     * @param  array<string, mixed>  $preset
     */
    public static function dump(array $preset): string
    {
        return Yaml::dump(array_intersect_key($preset, array_flip(self::KEYS)), 2, 2);
    }

    /**
     * A preset from shared YAML, checked.
     *
     * @return array{title: string, description: string, find: string, replace: string, mode: string, case_sensitive: bool, preserve_case: bool}
     *
     * @throws InvalidQuery when it is not a preset, or its search would not run
     */
    public static function parse(string $yaml): array
    {
        try {
            $values = Yaml::parse($yaml);
        } catch (ParseException $e) {
            throw new InvalidQuery('This is not valid YAML: '.$e->getMessage());
        }

        if (! is_array($values)) {
            throw new InvalidQuery('This is not a preset. A preset has a title, find and replace.');
        }

        return self::validate($values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{title: string, description: string, find: string, replace: string, mode: string, case_sensitive: bool, preserve_case: bool}
     */
    private static function validate(array $values): array
    {
        $title = trim((string) ($values['title'] ?? ''));

        if ($title === '') {
            throw new InvalidQuery('Give the preset a title.');
        }

        // Building the query is the check: the same refusal a search gives.
        $query = Query::fromArray($values)->toArray();

        return ['title' => $title, 'description' => trim((string) ($values['description'] ?? ''))] + $query;
    }
}
