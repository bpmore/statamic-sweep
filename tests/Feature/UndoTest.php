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
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Git\CommitJob;
use Statamic\Git\Subscriber as GitSubscriber;

beforeEach(function () {
    config(['sweep.path' => $this->sweepPath = storage_path('framework/testing/sweep-'.uniqid())]);

    Collection::make('pages')->save();
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'intro', 'field' => ['type' => 'textarea']],
        ['handle' => 'body', 'field' => ['type' => 'bard']],
        ['handle' => 'notes', 'field' => ['type' => 'markdown']],
    ]])->save();
});

afterEach(function () {
    File::deleteDirectory($this->sweepPath);
    Carbon::setTestNow();
});

function sweepAndApply(string $find = 'Acme', string $replace = 'Apex', Mode $mode = Mode::Literal, ?Scope $scope = null): Sweep
{
    $sweep = app(Sweeps::class)->start(new Query($find, $replace, $mode, false), $scope ?? new Scope, null, now: true);
    app(Sweeps::class)->apply($sweep, null, now: true);

    return app(SweepStore::class)->find($sweep->id);
}

function undoSweep(Sweep $sweep): Sweep
{
    expect(app(Sweeps::class)->undo($sweep, null, now: true))->toBeTrue();

    return app(SweepStore::class)->find($sweep->id);
}

function fieldValues(string $id): array
{
    return collect(Entry::find($id)->data()->all())->only(['title', 'intro', 'body', 'notes'])->all();
}

it('puts every untouched item back byte for byte, and lists the edited ones as conflicts: acceptance 5', function () {
    $originals = [
        'a' => ['title' => 'Acme, ACME and acme', 'intro' => "Line one Acme\r\nLine two acme  \r\n\r\n", 'notes' => "  # Acme\n\n\tTabbed acme\n\n\n"],
        'b' => ['title' => 'Ünïcödé Acme — “quoted” acme 🧹', 'body' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Acme '],
                ['type' => 'text', 'text' => 'ACME', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'https://acme.test/?q=acme&x=1']]]],
                ['type' => 'text', 'text' => ' trailing '],
            ]],
        ]],
        'c' => ['title' => 'acme 123', 'intro' => '0 acme 0.0 null ~ yes'],
        'edited' => ['title' => 'Acme edited later', 'intro' => 'Acme stays'],
    ];

    foreach ($originals as $id => $data) {
        Entry::make()->id($id)->collection('pages')->slug($id)->data($data)->save();
    }

    $sweep = sweepAndApply('acme', 'Apex Industries');
    expect($sweep->applyTally[Applier::APPLIED])->toBe(4)
        ->and(Entry::find('a')->get('title'))->toBe('Apex Industries, Apex Industries and Apex Industries');

    // An editor changes one swept field of one entry after the sweep.
    Entry::find('edited')->set('title', 'Apex Industries, edited by hand')->save();

    $sweep = undoSweep($sweep);

    foreach (['a', 'b', 'c'] as $id) {
        expect(fieldValues($id))->toBe($originals[$id]);
    }

    $edited = collect(iterator_to_array(app(SweepStore::class)->records($sweep)))->firstWhere('reference', 'entry::edited');

    expect(Entry::find('edited')->get('title'))->toBe('Apex Industries, edited by hand')
        ->and(Entry::find('edited')->get('intro'))->toBe('Acme stays')
        ->and($edited['undo'])->toBe(Applier::PARTIAL)
        ->and(array_column($edited['changes'], 'undo', 0))->toBe(['conflict', 'restored'])
        ->and($sweep->status)->toBe(Status::PartiallyUndone)
        ->and($sweep->undoTally)->toMatchArray([Applier::RESTORED => 3, Applier::PARTIAL => 1, Applier::CONFLICT => 0]);
});

it('marks a sweep undone when everything went back', function () {
    Entry::make()->id('a')->collection('pages')->slug('a')->data(['title' => 'Acme'])->save();

    $sweep = undoSweep(sweepAndApply());

    expect($sweep->status)->toBe(Status::Undone)
        ->and(Entry::find('a')->get('title'))->toBe('Acme')
        ->and($sweep->undoneAt)->not->toBeNull();
});

it('lists a field edited back and forth as a conflict, and changes nothing in it', function () {
    Entry::make()->id('a')->collection('pages')->slug('a')->data(['title' => 'Acme'])->save();
    $sweep = sweepAndApply();
    Entry::find('a')->set('title', 'Something else')->save();

    $sweep = undoSweep($sweep);

    expect(Entry::find('a')->get('title'))->toBe('Something else')
        ->and($sweep->undoTally[Applier::CONFLICT])->toBe(1)
        ->and($sweep->status)->toBe(Status::PartiallyUndone);
});

it('undoes only the matches that were applied, leaving deselected ones alone', function () {
    Entry::make()->id('a')->collection('pages')->slug('a')->data(['title' => 'Acme Acme'])->save();
    $sweep = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, null, now: true);
    app(SweepStore::class)->updateChunk($sweep->id, 1, function (array $records) {
        $records[0]['changes'][0]['hits'][0]['selected'] = false;

        return $records;
    });
    app(Sweeps::class)->apply($sweep, null, now: true);
    expect(Entry::find('a')->get('title'))->toBe('Acme Apex');

    undoSweep(app(SweepStore::class)->find($sweep->id));

    expect(Entry::find('a')->get('title'))->toBe('Acme Acme');
});

it('finds a term again by its new slug when the sweep changed it', function () {
    Taxonomy::make('tags')->save();
    Term::make()->taxonomy('tags')->slug('acme')->dataForLocale('default', ['title' => 'Acme'])->save();

    $sweep = sweepAndApply('acme', 'apex', scope: new Scope(collections: [], slugs: true));

    // Renamed the way the control panel renames a term: slug(), then save().
    expect(Term::find('tags::apex')?->title())->toBe('apex');

    undoSweep($sweep);

    expect(Term::find('tags::acme')?->title())->toBe('Acme');
});

it('can be undone for the configured number of days, and not after', function () {
    config(['sweep.undo_days' => 30]);
    Entry::make()->id('a')->collection('pages')->slug('a')->data(['title' => 'Acme'])->save();
    Carbon::setTestNow('2026-10-01 09:00:00');
    $sweep = sweepAndApply();

    Carbon::setTestNow('2026-10-31 08:59:00');
    expect(app(Sweeps::class)->undoable($sweep))->toBeTrue();

    Carbon::setTestNow('2026-10-31 09:01:00');
    expect(app(Sweeps::class)->undoable($sweep))->toBeFalse()
        ->and(app(Sweeps::class)->undo($sweep, null, now: true))->toBeFalse()
        ->and(Entry::find('a')->get('title'))->toBe('Apex');
});

it('refuses to undo a sweep that was never applied, or twice', function () {
    Entry::make()->id('a')->collection('pages')->slug('a')->data(['title' => 'Acme'])->save();
    $preview = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, null, now: true);

    expect(app(Sweeps::class)->undo($preview, null, now: true))->toBeFalse();

    $sweep = undoSweep(sweepAndApply());

    expect(app(Sweeps::class)->undo($sweep, null, now: true))->toBeFalse();
});

it('makes one Git commit for the undo too', function () {
    for ($i = 1; $i <= 20; $i++) {
        Entry::make()->id("p{$i}")->collection('pages')->slug("p{$i}")->data(['title' => 'Acme'])->save();
    }
    $sweep = sweepAndApply();

    config(['statamic.editions.pro' => true, 'statamic.git.enabled' => true, 'statamic.git.automatic' => true]);
    Event::subscribe(GitSubscriber::class);
    Queue::fake([CommitJob::class]);

    undoSweep($sweep);

    Queue::assertPushed(CommitJob::class, 1);
    Queue::assertPushed(CommitJob::class, fn (CommitJob $job) => $job->message === 'Undo sweep: Acme → Apex');
});

it('prunes undo data after the undo period and keeps the sweep in the history', function () {
    config(['sweep.undo_days' => 30]);
    Entry::make()->id('a')->collection('pages')->slug('a')->data(['title' => 'Acme'])->save();
    Carbon::setTestNow('2026-09-01 09:00:00');
    $old = sweepAndApply();
    $oldPreview = app(Sweeps::class)->start(new Query('Apex', 'Acme'), new Scope, null, now: true);
    Carbon::setTestNow('2026-10-20 09:00:00');
    $recent = app(Sweeps::class)->start(new Query('Apex', 'Acme'), new Scope, null, now: true);

    $this->artisan('sweep:prune')->expectsOutputToContain('2 sweeps')->assertSuccessful();

    $store = app(SweepStore::class);

    expect($store->find($old->id)->pruned)->toBeTrue()
        ->and($store->find($old->id)->status)->toBe(Status::Applied)
        ->and(File::isDirectory($this->sweepPath.'/'.$old->id))->toBeFalse()
        ->and($store->find($oldPreview->id)->pruned)->toBeTrue()
        ->and($store->find($recent->id)->pruned)->toBeFalse()
        ->and(File::isDirectory($this->sweepPath.'/'.$recent->id))->toBeTrue()
        ->and(app(Sweeps::class)->undoable($store->find($old->id)))->toBeFalse();
});

it('schedules the prune daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'sweep:prune'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
