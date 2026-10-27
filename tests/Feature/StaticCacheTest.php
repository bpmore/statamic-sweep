<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Sweeps\Sweeps;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\StaticCaching\Cacher;
use Statamic\StaticCaching\Invalidate;
use Statamic\StaticCaching\Invalidator;

/**
 * Sweep leaves Statamic's own static cache invalidation running on every
 * save, so each changed item is invalidated by the site's own rules (an
 * entry's URL, plus whatever the site's invalidation config adds). This
 * checks that it runs for exactly the items a sweep changed.
 */
it('invalidates the static cache for each item it changed, and only those', function () {
    config(['sweep.path' => $path = storage_path('framework/testing/sweep-'.uniqid())]);
    Collection::make('pages')->routes('/{slug}')->save();
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]])->save();
    Entry::make()->id('changed')->collection('pages')->slug('changed')->data(['title' => 'Acme'])->save();
    Entry::make()->id('untouched')->collection('pages')->slug('untouched')->data(['title' => 'Plain'])->save();

    $sweep = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, null, now: true);

    // Statamic subscribes this at boot when a strategy is set; the test sets
    // one afterwards, so it subscribes it the same way.
    config(['statamic.static_caching.strategy' => 'half']);
    $refreshed = [];
    $invalidator = Mockery::mock(Invalidator::class);
    $invalidator->shouldReceive('refresh')->andReturnUsing(function ($item) use (&$refreshed) {
        $refreshed[] = $item->id();
    });
    app()->instance(Invalidator::class, $invalidator);
    app()->instance(Cacher::class, Mockery::mock(Cacher::class));
    Event::subscribe(Invalidate::class);

    app(Sweeps::class)->apply($sweep, null, now: true);
    File::deleteDirectory($path);

    expect($refreshed)->toBe(['changed']);
});
