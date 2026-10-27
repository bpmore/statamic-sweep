<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Jobs\SearchSweep;
use Bpmore\Sweep\Sweeps\Status;
use Bpmore\Sweep\Sweeps\Sweeps;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

beforeEach(function () {
    config(['sweep.path' => $this->sweepPath = storage_path('framework/testing/sweep-'.uniqid())]);

    Collection::make('blog')->save();
    Blueprint::make('post')->setNamespace('collections.blog')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]])->save();

    for ($i = 1; $i <= 250; $i++) {
        Entry::make()->id(sprintf('e%03d', $i))->collection('blog')->slug("e{$i}")->data(['title' => $i % 5 === 0 ? 'Plain' : "Acme {$i}"])->save();
    }
});

afterEach(fn () => File::deleteDirectory($this->sweepPath));

it('searches now when asked, writing items a chunk at a time', function () {
    $sweep = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, null, now: true);
    $store = app(SweepStore::class);

    expect($sweep->status)->toBe(Status::Previewed)
        ->and($sweep->items)->toBe(200)
        ->and($sweep->chunks)->toBe(2)
        ->and($sweep->tally->searched)->toBe(250)
        ->and($sweep->tally->matches)->toBe(200)
        ->and($sweep->progress)->toBe(['phase' => 'search', 'done' => 250])
        ->and(count($store->readChunk($sweep->id, 1)))->toBe(100)
        ->and(count($store->readChunk($sweep->id, 2)))->toBe(100)
        ->and($store->readChunk($sweep->id, 1)[0])->toMatchArray(['reference' => 'entry::e001', 'kind' => 'entry', 'title' => 'Acme 1']);
});

it('queues the search by default, and leaves the sweep searching until it runs', function () {
    Queue::fake();
    $user = User::make()->id('sam')->email('sam@example.com')->makeSuper()->save();

    $sweep = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, $user);

    expect($sweep->status)->toBe(Status::Searching)
        ->and($sweep->createdBy)->toBe(['id' => 'sam', 'name' => 'sam@example.com']);

    Queue::assertPushed(SearchSweep::class, fn (SearchSweep $job) => $job->sweepId === $sweep->id);
});

it('searches as the person who started it, and fails rather than search as nobody if they are gone', function () {
    Queue::fake();
    $user = User::make()->id('gone')->email('gone@example.com')->makeSuper()->save();
    $sweep = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, $user);
    $user->delete();

    app(Sweeps::class)->search($sweep->id);

    $sweep = app(SweepStore::class)->find($sweep->id);

    expect($sweep->status)->toBe(Status::Failed)
        ->and($sweep->error)->toContain('no longer has an account')
        ->and($sweep->items)->toBe(0);
});

it('does nothing to a sweep that is not waiting to be searched', function () {
    $sweep = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, null, now: true);
    $before = app(SweepStore::class)->find($sweep->id)->toArray();

    app(Sweeps::class)->search($sweep->id);
    app(Sweeps::class)->search('01j00000000000000000000000');

    expect(app(SweepStore::class)->find($sweep->id)->toArray())->toBe($before);
});

it('runs from Artisan, prints the counts and changes nothing', function () {
    $this->artisan('sweep:run', ['--find' => 'Acme', '--replace' => 'Apex', '--scope' => ['collection:blog']])
        ->expectsOutputToContain('Items searched')
        ->expectsOutputToContain('Nothing has been changed yet')
        ->assertSuccessful();

    expect(Entry::find('e001')->get('title'))->toBe('Acme 1')
        ->and(app(SweepStore::class)->all())->toHaveCount(1)
        ->and(app(SweepStore::class)->all()[0]->tally->matches)->toBe(200);
});

it('refuses a bad pattern or scope from Artisan before searching anything', function () {
    $this->artisan('sweep:run', ['--find' => '(abc', '--regex' => true])
        ->expectsOutputToContain('The regular expression does not work')
        ->assertExitCode(2);

    $this->artisan('sweep:run', ['--find' => 'a', '--scope' => ['blog']])
        ->expectsOutputToContain('collection:blog')
        ->assertExitCode(2);

    $this->artisan('sweep:run', ['--find' => 'a', '--regex' => true, '--word' => true])->assertExitCode(2);

    expect(app(SweepStore::class)->all())->toBe([]);
});
