<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\Mode;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Sweeps\Applier;
use Bpmore\Sweep\Sweeps\Status;
use Bpmore\Sweep\Sweeps\Sweep;
use Bpmore\Sweep\Sweeps\Sweeps;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Git\CommitJob;
use Statamic\Git\Subscriber as GitSubscriber;

beforeEach(function () {
    config(['sweep.path' => $this->sweepPath = storage_path('framework/testing/sweep-'.uniqid())]);

    Collection::make('pages')->save();
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'body', 'field' => ['type' => 'bard', 'sets' => ['main' => ['sets' => [
            'quote' => ['fields' => [['handle' => 'text', 'field' => ['type' => 'text']]]],
        ]]]]],
        ['handle' => 'rows', 'field' => ['type' => 'grid', 'fields' => [
            ['handle' => 'blocks', 'field' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => [
                'line' => ['fields' => [['handle' => 'words', 'field' => ['type' => 'text']], ['handle' => 'note', 'field' => ['type' => 'text']]]],
            ]]]]],
        ]]],
    ]])->save();

    $this->admin = User::make()->id('admin')->email('admin@example.com')->makeSuper()->save();
});

afterEach(fn () => File::deleteDirectory($this->sweepPath));

function makePage(string $id, array $data): void
{
    Entry::make()->id($id)->collection('pages')->slug($id)->data($data)->save();
}

function previewSweep(string $find = 'Acme', string $replace = 'Apex', Mode $mode = Mode::Literal, ?Scope $scope = null): Sweep
{
    return app(Sweeps::class)->start(new Query($find, $replace, $mode), $scope ?? new Scope, null, now: true);
}

function applySweep(Sweep $sweep, $user = null): Sweep
{
    expect(app(Sweeps::class)->apply($sweep, $user, now: true))->toBeTrue();

    return app(SweepStore::class)->find($sweep->id);
}

function sweepOutcomes(Sweep $sweep): array
{
    return collect(iterator_to_array(app(SweepStore::class)->records($sweep)))->pluck('outcome', 'reference')->all();
}

it('changes only the bold text in Bard and keeps the mark, end to end: acceptance 1', function () {
    $body = [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'We are '],
        ['type' => 'text', 'text' => 'Acme', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => '.'],
    ]]];
    makePage('p1', ['title' => 'Home', 'body' => $body]);

    $sweep = applySweep(previewSweep());

    $expected = $body;
    $expected[0]['content'][1]['text'] = 'Apex';

    expect($sweep->status)->toBe(Status::Applied)
        ->and(Entry::find('p1')->get('body'))->toBe($expected)
        ->and($sweep->applyTally['applied'])->toBe(1);
});

it('leaves a split match unchanged, end to end: acceptance 2', function () {
    $body = [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Acme', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/x']]]],
        ['type' => 'text', 'text' => ' Corporation'],
    ]]];
    makePage('p1', ['title' => 'Home', 'body' => $body]);

    $sweep = previewSweep('Acme Corporation', 'Apex Inc');
    expect($sweep->tally->splits)->toBe(1);

    $sweep = applySweep($sweep);

    expect(Entry::find('p1')->get('body'))->toBe($body)
        ->and(sweepOutcomes($sweep))->toBe(['entry::p1' => Applier::DESELECTED]);
});

it('replaces in a Replicator set in a Grid row and leaves the siblings, end to end: acceptance 3', function () {
    $rows = [['id' => 'r1', 'blocks' => [['id' => 's1', 'type' => 'line', 'enabled' => true, 'words' => 'Acme words', 'note' => 'Keep me']]]];
    makePage('p1', ['title' => 'Plain', 'rows' => $rows]);

    applySweep(previewSweep());

    $expected = $rows;
    $expected[0]['blocks'][0]['words'] = 'Apex words';

    expect(Entry::find('p1')->get('rows'))->toBe($expected)
        ->and(Entry::find('p1')->get('title'))->toBe('Plain');
});

it('skips and flags an entry edited between preview and apply: acceptance 4', function () {
    makePage('edited', ['title' => 'Acme one']);
    makePage('untouched', ['title' => 'Acme two']);
    $sweep = previewSweep();

    // An editor changes a different field of one entry.
    Entry::find('edited')->set('intro', 'New intro')->save();

    $sweep = applySweep($sweep);

    expect(sweepOutcomes($sweep))->toBe(['entry::edited' => Applier::CHANGED, 'entry::untouched' => Applier::APPLIED])
        ->and(Entry::find('edited')->get('title'))->toBe('Acme one')
        ->and(Entry::find('untouched')->get('title'))->toBe('Apex two')
        ->and($sweep->applyTally[Applier::CHANGED])->toBe(1);
});

it('fills in capture groups when applied: acceptance 8', function () {
    makePage('p1', ['title' => 'Call 555-1234']);

    applySweep(previewSweep('(\d{3})-(\d{4})', '$1.$2', Mode::Regex));

    expect(Entry::find('p1')->get('title'))->toBe('Call 555.1234');
});

it('applies only the matches and items left selected', function () {
    makePage('p1', ['title' => 'Acme Acme Acme']);
    makePage('p2', ['title' => 'Acme']);
    $sweep = previewSweep();

    app(SweepStore::class)->updateChunk($sweep->id, 1, function (array $records) {
        $records[0]['changes'][0]['hits'][1]['selected'] = false;
        $records[1]['selected'] = false;

        return $records;
    });

    $sweep = applySweep($sweep);

    expect(Entry::find('p1')->get('title'))->toBe('Apex Acme Apex')
        ->and(Entry::find('p2')->get('title'))->toBe('Acme')
        ->and(sweepOutcomes($sweep))->toBe(['entry::p1' => Applier::APPLIED, 'entry::p2' => Applier::DESELECTED]);
});

it('saves through Statamic, so events run, as the person applying', function () {
    makePage('p1', ['title' => 'Acme']);
    $sweep = previewSweep();
    Event::fake([EntrySaved::class]);

    applySweep($sweep, $this->admin);

    Event::assertDispatched(EntrySaved::class, fn (EntrySaved $e) => $e->entry->id() === 'p1');

    expect(Entry::find('p1')->get('updated_by'))->toBe('admin');
});

it('records a revision with the sweep\'s message where the collection keeps them', function () {
    config(['statamic.editions.pro' => true, 'statamic.revisions.enabled' => true, 'statamic.revisions.path' => $this->sweepPath.'/revisions']);
    Collection::find('pages')->revisionsEnabled(true)->save();
    makePage('p1', ['title' => 'Acme']);

    applySweep(previewSweep(), $this->admin);

    $revision = Entry::find('p1')->latestRevision();

    expect($revision)->not->toBeNull()
        ->and($revision->message())->toBe('Sweep: Acme → Apex')
        ->and($revision->user()?->id())->toBe('admin');
});

it('skips an entry with unpublished changes, which would overwrite the sweep when published', function () {
    config(['statamic.editions.pro' => true, 'statamic.revisions.enabled' => true, 'statamic.revisions.path' => $this->sweepPath.'/revisions']);
    Collection::find('pages')->revisionsEnabled(true)->save();
    makePage('p1', ['title' => 'Acme']);
    $sweep = previewSweep();
    Entry::find('p1')->makeWorkingCopy()->save();

    $sweep = applySweep($sweep);

    expect(sweepOutcomes($sweep))->toBe(['entry::p1' => Applier::WORKING_COPY])
        ->and(Entry::find('p1')->get('title'))->toBe('Acme');
});

it('makes one Git commit for a 200-item sweep, with the sweep\'s message: acceptance 7', function () {
    for ($i = 1; $i <= 200; $i++) {
        makePage("p{$i}", ['title' => "Acme {$i}"]);
    }
    $sweep = previewSweep();

    // Statamic subscribes its Git listener at boot on Pro sites; this test
    // turns Pro and Git on afterwards, so it subscribes the same way.
    config(['statamic.editions.pro' => true, 'statamic.git.enabled' => true, 'statamic.git.automatic' => true]);
    Event::subscribe(GitSubscriber::class);
    Queue::fake([CommitJob::class]);

    $sweep = applySweep($sweep, $this->admin);

    expect($sweep->applyTally[Applier::APPLIED])->toBe(200);
    Queue::assertPushed(CommitJob::class, 1);
    Queue::assertPushed(CommitJob::class, fn (CommitJob $job) => $job->message === 'Sweep: Acme → Apex');

    // And Git automation is back on afterwards, for every other save.
    // (Saving again would not show it: CommitJob is unique while one is
    // pending, so Statamic itself folds a second save into the first.)
    $gitListeners = collect(app('events')->getRawListeners()[EntrySaved::class] ?? [])
        ->filter(fn ($listener) => $listener === GitSubscriber::class.'@commit');

    expect($gitListeners)->toHaveCount(1);
});

it('refuses to apply a sweep twice, or one that is not a preview', function () {
    makePage('p1', ['title' => 'Acme']);
    $sweep = applySweep(previewSweep());

    expect(app(Sweeps::class)->apply($sweep, null, now: true))->toBeFalse()
        ->and(Entry::find('p1')->get('title'))->toBe('Apex');
});

it('marks an entry deleted since the preview as missing', function () {
    makePage('p1', ['title' => 'Acme']);
    $sweep = previewSweep();
    Entry::find('p1')->delete();

    expect(sweepOutcomes(applySweep($sweep)))->toBe(['entry::p1' => Applier::MISSING]);
});
