<?php

declare(strict_types=1);

use Bpmore\Sweep\Tests\TestCase;

// Everything under Feature gets a booted Statamic. The engine is plain PHP
// and is tested under Unit without one, so it stays fast.
uses(TestCase::class)->in('Feature');

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
