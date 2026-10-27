<?php

declare(strict_types=1);

namespace Bpmore\Sweep;

use Statamic\Providers\AddonServiceProvider;

/**
 * config/sweep.php is merged and published (`--tag=sweep-config`) by
 * AddonServiceProvider itself, from the addon's slug, so it is not repeated
 * here.
 */
class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => ['resources/js/cp.js'],
        'publicDirectory' => 'resources/dist',
    ];

    // The product is "Sweep" everywhere a person sees it: views, config,
    // commands, never the repository name.
    protected $viewNamespace = 'sweep';

    public function bootAddon(): void
    {
        //
    }
}
