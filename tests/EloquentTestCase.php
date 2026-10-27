<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Tests;

use Bpmore\Sweep\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Eloquent\Entries\UuidEntryModel;
use Statamic\Eloquent\ServiceProvider as EloquentServiceProvider;
use Statamic\Testing\AddonTestCase;

/**
 * The same Statamic, with content in a database through statamic/eloquent-
 * driver instead of in files, the way the driver's own tests set it up.
 *
 * Content kinds Sweep reads and writes are switched to the database.
 * Blueprints and fieldsets stay as files: Sweep only reads them, and the
 * tests make them the same way either way.
 */
abstract class EloquentTestCase extends AddonTestCase
{
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    private const IN_DATABASE = [
        'asset_containers', 'assets', 'collections', 'collection_trees', 'entries',
        'global_sets', 'global_set_variables', 'navigations', 'navigation_trees',
        'revisions', 'taxonomies', 'terms',
    ];

    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), EloquentServiceProvider::class];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.eloquent-driver', require __DIR__.'/../vendor/statamic/eloquent-driver/config/eloquent-driver.php');

        foreach (self::IN_DATABASE as $kind) {
            $app['config']->set("statamic.eloquent-driver.{$kind}.driver", 'eloquent');
        }

        // Entry ids as strings, as on a site moved from flat files, so the
        // tests can name entries the same way the flat-file tests do.
        $app['config']->set('statamic.eloquent-driver.entries.model', UuidEntryModel::class);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.stache.watcher', false);
    }

    protected function defineDatabaseMigrations()
    {
        $migrations = __DIR__.'/../vendor/statamic/eloquent-driver/database/migrations';

        $this->loadMigrationsFrom($migrations);
        $this->loadMigrationsFrom($migrations.'/entries/2024_03_07_100000_create_entries_table_with_string_ids.php');
    }
}
