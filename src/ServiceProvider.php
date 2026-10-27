<?php

declare(strict_types=1);

namespace Bpmore\Sweep;

use Bpmore\Sweep\Auth\Permissions;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\User;
use Statamic\Providers\AddonServiceProvider;

/**
 * config/sweep.php and lang/ are loaded and published (`--tag=sweep-config`,
 * `--tag=sweep-translations`) by AddonServiceProvider itself, from the
 * addon's slug, so neither is repeated here.
 */
class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $vite = [
        'input' => ['resources/js/cp.js'],
        'publicDirectory' => 'resources/dist',
    ];

    // The product is "Sweep" everywhere a person sees it: views, config,
    // commands, never the repository name.
    protected $viewNamespace = 'sweep';

    public function bootAddon(): void
    {
        $this->bootPermissions();
        $this->bootNavigation();
    }

    private function bootPermissions(): void
    {
        Permission::extend(function (): void {
            Permission::group('sweep', __('sweep::messages.sweep'), function (): void {
                Permission::register(Permissions::USE, function ($permission): void {
                    $permission
                        ->label(__('sweep::messages.permission_use'))
                        ->description(__('sweep::messages.permission_use_description'))
                        ->children([
                            Permission::make(Permissions::APPLY)
                                ->label(__('sweep::messages.permission_apply'))
                                ->description(__('sweep::messages.permission_apply_description')),
                            Permission::make(Permissions::UNDO)
                                ->label(__('sweep::messages.permission_undo'))
                                ->description(__('sweep::messages.permission_undo_description')),
                            Permission::make(Permissions::MANAGE_PRESETS)
                                ->label(__('sweep::messages.permission_manage_presets'))
                                ->description(__('sweep::messages.permission_manage_presets_description')),
                        ]);
                });
            });
        });
    }

    private function bootNavigation(): void
    {
        Nav::extend(function ($nav): void {
            // A route cache built before this addon was installed does not
            // hold its CP routes, and Nav::extend runs on every control panel
            // request including login. Without this guard a stale cache takes
            // the whole control panel down with "Route not defined".
            if (! Route::has('statamic.cp.sweep.index')) {
                return;
            }

            if (! Permissions::mayUse(User::current())) {
                return;
            }

            $nav->tools(__('sweep::messages.sweep'))
                ->route('sweep.index')
                ->icon('replace');
        });
    }
}
