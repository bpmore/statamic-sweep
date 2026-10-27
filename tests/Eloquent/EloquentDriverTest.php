<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Sweeps\Applier;
use Bpmore\Sweep\Sweeps\Status;
use Bpmore\Sweep\Sweeps\Sweep;
use Bpmore\Sweep\Sweeps\Sweeps;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

/**
 * Sweep on a site whose content is in a database. Everything goes through
 * Statamic's facades, so the same code should work; these tests check it
 * does, including that a value read back from the database fingerprints the
 * same as when it was previewed.
 */
beforeEach(function () {
    config(['sweep.path' => $this->sweepPath = storage_path('framework/testing/sweep-'.uniqid())]);

    Collection::make('pages')->save();
    Blueprint::make('page')->setNamespace('collections.pages')->setContents(['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'body', 'field' => ['type' => 'bard', 'sets' => ['main' => ['sets' => [
            'table' => ['fields' => [['handle' => 'rows', 'field' => ['type' => 'grid', 'fields' => [
                ['handle' => 'parts', 'field' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => [
                    'line' => ['fields' => [['handle' => 'words', 'field' => ['type' => 'text']], ['handle' => 'note', 'field' => ['type' => 'text']]]],
                ]]]]],
            ]]]]],
        ]]]]],
    ]])->save();

    $this->body = [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'We are '],
            ['type' => 'text', 'text' => 'Acme', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ". Line\r\nbreak  "],
        ]],
        ['type' => 'set', 'attrs' => ['id' => 's1', 'values' => ['type' => 'table', 'rows' => [
            ['id' => 'r1', 'parts' => [['id' => 'p1', 'type' => 'line', 'enabled' => true, 'words' => 'Acme words', 'note' => 'Keep']]],
        ]]]],
    ];

    Entry::make()->id('e1')->collection('pages')->slug('e1')->data(['title' => 'Acme home', 'body' => $this->body])->save();
    Entry::make()->id('e2')->collection('pages')->slug('e2')->data(['title' => 'Acme two'])->save();

    Taxonomy::make('tags')->save();
    Term::make()->taxonomy('tags')->slug('acme')->dataForLocale('default', ['title' => 'Acme tag'])->save();

    GlobalSet::make('footer')->title('Footer')->save();
    Blueprint::make('footer')->setNamespace('globals')->setContents(['fields' => [['handle' => 'copyright', 'field' => ['type' => 'text']]]])->save();
    GlobalSet::find('footer')->inDefaultSite()->data(['copyright' => '© Acme'])->save();

    Nav::make('main')->title('Main')->save();
    // The driver saves a brand-new tree with no branches; the control panel
    // creates a tree and then saves its branches, so this does too.
    Nav::find('main')->makeTree('default')->save();
    Nav::find('main')->in('default')->tree([['id' => 'n1', 'title' => 'Acme', 'url' => '/acme']])->save();
});

afterEach(fn () => File::deleteDirectory($this->sweepPath));

function eloquentSweep(): Sweep
{
    return app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope, null, now: true);
}

it('really is reading entries from the database', function () {
    expect(DB::table('entries')->count())->toBe(2)
        ->and(DB::table('global_set_variables')->count())->toBe(1)
        ->and(DB::table('trees')->count())->toBe(1)
        ->and(DB::table('taxonomy_terms')->count())->toBe(1);
});

it('previews, applies and undoes entries, terms, globals and navigation', function () {
    $sweep = eloquentSweep();

    expect($sweep->tally->matchedItems)->toBe(5)
        ->and($sweep->tally->matches)->toBe(7);

    app(Sweeps::class)->apply($sweep, null, now: true);
    $sweep = app(SweepStore::class)->find($sweep->id);

    $expectedBody = $this->body;
    $expectedBody[0]['content'][1]['text'] = 'Apex';
    $expectedBody[1]['attrs']['values']['rows'][0]['parts'][0]['words'] = 'Apex words';

    expect($sweep->applyTally[Applier::APPLIED])->toBe(5)
        ->and(Entry::find('e1')->get('body'))->toBe($expectedBody)
        ->and(Entry::find('e1')->get('title'))->toBe('Apex home')
        ->and(Term::find('tags::acme')->in('default')->get('title'))->toBe('Apex tag')
        ->and(GlobalSet::find('footer')->inDefaultSite()->get('copyright'))->toBe('© Apex')
        ->and(Nav::find('main')->in('default')->tree()[0]['title'])->toBe('Apex');

    app(Sweeps::class)->undo($sweep, null, now: true);
    $sweep = app(SweepStore::class)->find($sweep->id);

    expect($sweep->status)->toBe(Status::Undone)
        ->and(Entry::find('e1')->get('body'))->toBe($this->body)
        ->and(Entry::find('e1')->get('title'))->toBe('Acme home')
        ->and(Term::find('tags::acme')->in('default')->get('title'))->toBe('Acme tag')
        ->and(GlobalSet::find('footer')->inDefaultSite()->get('copyright'))->toBe('© Acme')
        ->and(Nav::find('main')->in('default')->tree()[0]['title'])->toBe('Acme');
});

it('skips an entry edited in the database between preview and apply', function () {
    $sweep = eloquentSweep();
    Entry::find('e2')->set('body', [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Edited']]]])->save();

    app(Sweeps::class)->apply($sweep, null, now: true);

    $outcomes = collect(iterator_to_array(app(SweepStore::class)->records($sweep)))->pluck('outcome', 'reference');

    expect($outcomes['entry::e2'])->toBe(Applier::CHANGED)
        ->and($outcomes['entry::e1'])->toBe(Applier::APPLIED)
        ->and(Entry::find('e2')->get('title'))->toBe('Acme two');
});
