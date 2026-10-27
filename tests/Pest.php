<?php

declare(strict_types=1);

use Bpmore\Sweep\Tests\EloquentTestCase;
use Bpmore\Sweep\Tests\TestCase;
use Statamic\Facades\Site;

// Everything under Feature gets a booted Statamic. The engine is plain PHP
// and is tested under Unit without one, so it stays fast.
uses(TestCase::class)->in('Feature');

// The same flows with content in a database, through statamic/eloquent-driver.
// Every Statamic 6 version of the driver needs PHP 8.3, so CI removes it and
// skips this group on PHP 8.2.
uses(EloquentTestCase::class)->group('eloquent')->in('Eloquent');

/**
 * The repository root.
 *
 * Not base_path(): under Testbench that is the skeleton application, not this
 * addon.
 */
function repoPath(string $relative = ''): string
{
    $root = dirname(__DIR__);

    return $relative === '' ? $root : $root.'/'.$relative;
}

/**
 * PHP files under the given directories, keyed by their path relative to the
 * repository root so a failure names the file.
 *
 * @param  list<string>  $directories
 * @return array<string, string>
 */
function phpSources(array $directories): array
{
    $root = dirname(__DIR__);
    $files = [];

    foreach ($directories as $directory) {
        if (! is_dir($root.'/'.$directory)) {
            continue;
        }
        $iterator = new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS);
        foreach (new RecursiveIteratorIterator($iterator) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $files[substr($file->getPathname(), strlen($root) + 1)] = $file->getPathname();
        }
    }

    ksort($files);

    return $files;
}

/**
 * The namespaced names a PHP file actually references, with comments and
 * string literals excluded.
 *
 * @return list<string>
 */
function referencedNames(string $file): array
{
    $names = [];

    foreach (token_get_all((string) file_get_contents($file)) as $token) {
        if (! is_array($token)) {
            continue;
        }
        if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
            $names[] = ltrim($token[1], '\\');
        }
    }

    return $names;
}

/**
 * Turns this test's site into a multisite with the given site handles. Three
 * or more sites, and any second site, are a Pro feature.
 *
 * @param  list<string>  $handles
 */
function useSites(array $handles = ['en', 'fr']): void
{
    config(['statamic.editions.pro' => true, 'statamic.system.multisite' => true]);

    Site::setSites(collect($handles)->mapWithKeys(fn (string $handle, int $i): array => [
        $handle => ['name' => strtoupper($handle), 'url' => $i === 0 ? '/' : "/{$handle}/", 'locale' => $handle === 'fr' ? 'fr_FR' : 'en_US'],
    ])->all());
}
