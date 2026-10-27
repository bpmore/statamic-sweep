<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Addon;

it('registers itself with Statamic as sweep', function () {
    $addon = Addon::get('bpmore/statamic-sweep');

    // The slug names the config file, the view namespace and the commands.
    // The display name is not asserted: Testbench builds a cut-down manifest
    // for the addon under test, so extra.statamic.name is only read on a real
    // install.
    expect($addon)->not->toBeNull()
        ->and($addon->slug())->toBe('sweep');
});

it('ships a Vite manifest where the control panel looks for it', function () {
    // AddonServiceProvider::registerVite points Laravel's Vite at
    // <buildDirectory>/manifest.json. Vite 6 writes .vite/manifest.json unless
    // told otherwise, which throws "Vite manifest not found" on every CP page.
    $manifest = json_decode((string) @file_get_contents(repoPath('resources/dist/build/manifest.json')), true);

    expect($manifest)->toBeArray()->toHaveKey('resources/js/cp.js');
    expect(is_file(repoPath('resources/dist/build/'.$manifest['resources/js/cp.js']['file'])))->toBeTrue();
});

it('merges its config', function () {
    expect(config('sweep.undo_days'))->toBe(30)
        ->and(config('sweep.path'))->toBe(storage_path('sweep'));
});

it('publishes its config under the sweep-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(null, 'sweep-config');

    expect(array_values($paths))->toBe([config_path('sweep.php')]);
});
