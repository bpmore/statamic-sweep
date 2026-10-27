<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\EntryItem;
use Bpmore\Sweep\Content\FieldTree;
use Bpmore\Sweep\Content\Items;
use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\InvalidQuery;
use Illuminate\Support\Carbon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

beforeEach(function () {
    useSites(['en', 'fr']);
    Carbon::setTestNow('2026-10-26 12:00:00');

    foreach (['blog', 'pages'] as $handle) {
        Collection::make($handle)->sites(['en', 'fr'])->dated($handle === 'blog')->futureDateBehavior($handle === 'blog' ? 'private' : 'public')->save();
    }

    Blueprint::make('post')->setNamespace('collections.blog')->setContents(['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'intro', 'field' => ['type' => 'textarea']],
    ]])->save();

    $make = fn (string $id, string $collection, array $data, bool $published = true, ?string $date = null) => tap(
        Entry::make()->id($id)->collection($collection)->locale('en')->slug($id)->published($published)->data($data),
        fn ($entry) => $date ? $entry->date($date) : null,
    )->save();

    $make('post-live', 'blog', ['title' => 'Live Acme', 'intro' => 'Acme'], date: '2026-01-01');
    $make('post-draft', 'blog', ['title' => 'Draft Acme'], published: false, date: '2026-01-01');
    $make('post-future', 'blog', ['title' => 'Future Acme'], date: '2027-01-01');
    $make('page-home', 'pages', ['title' => 'Home Acme']);

    Entry::find('page-home')->makeLocalization('fr')->id('page-home-fr')->data(['title' => 'Accueil Acme'])->save();
});

afterEach(fn () => Carbon::setTestNow());

function references(Scope $scope): array
{
    $references = array_map(fn ($item) => $item->reference(), iterator_to_array((new Items)->in($scope), false));
    sort($references);

    return $references;
}

it('lists every entry in every site when nothing narrows it', function () {
    expect(references(new Scope))->toBe(['entry::page-home', 'entry::page-home-fr', 'entry::post-draft', 'entry::post-future', 'entry::post-live']);
});

it('narrows by collection, by site, and by status', function () {
    expect(references(new Scope(collections: ['blog'])))->toBe(['entry::post-draft', 'entry::post-future', 'entry::post-live'])
        ->and(references(new Scope(sites: ['fr'])))->toBe(['entry::page-home-fr'])
        ->and(references(new Scope(collections: ['blog'], statuses: ['scheduled'])))->toBe(['entry::post-future'])
        ->and(references(new Scope(collections: ['blog'], statuses: ['draft'])))->toBe(['entry::post-draft'])
        ->and(references(new Scope(statuses: ['published'])))->toBe(['entry::page-home', 'entry::page-home-fr', 'entry::post-live']);
});

it('lists no entries when collections is an empty list', function () {
    expect(references(new Scope(collections: [])))->toBe([]);
});

it('reads a scope from the strings sweep:run takes', function () {
    $scope = Scope::fromStrings(['collection:blog', 'site:en', 'status:draft', 'field:title']);

    expect($scope->collections)->toBe(['blog'])
        ->and($scope->taxonomies)->toBe([])
        ->and($scope->globals)->toBe([])
        ->and($scope->sites)->toBe(['en'])
        ->and($scope->statuses)->toBe(['draft'])
        ->and($scope->handles)->toBe(['title'])
        ->and(Scope::fromStrings(['global:*'])->globals)->toBeNull()
        ->and(Scope::fromStrings(['global:*'])->collections)->toBe([])
        ->and(Scope::fromStrings(['site:fr'])->collections)->toBeNull()
        ->and(Scope::fromArray($scope->toArray())->toArray())->toBe($scope->toArray());

    expect(fn () => Scope::fromStrings(['blog']))->toThrow(InvalidQuery::class, 'collection:blog')
        ->and(fn () => Scope::fromStrings(['colection:blog']))->toThrow(InvalidQuery::class, 'not a kind of content')
        ->and(fn () => new Scope(statuses: ['live']))->toThrow(InvalidQuery::class, 'no entry status');
});

it('gives the engine only the values a localization owns', function () {
    $fr = (new Items)->find('entry::page-home-fr');

    expect($fr->data())->toBe(['title' => 'Accueil Acme'])
        ->and($fr->site())->toBe('fr')
        ->and($fr->title())->toBe('Accueil Acme')
        ->and($fr->kind())->toBe('entry');
});

it('reads the blueprint\'s fields, and offers the slug only when slugs are searched', function () {
    $item = (new Items)->find('entry::post-live');
    $handles = array_map(fn ($f) => $f->handle, $item->fields(new FieldTree));

    expect($handles)->toContain('title', 'intro', 'slug')
        ->and($item->data())->not->toHaveKey('slug')
        ->and((new Items)->findFor('entry::post-live', new Scope(slugs: true))->data()['slug'])->toBe('post-live');
});

it('writes only the values that changed, and the slug through the entry', function () {
    $item = (new Items)->findFor('entry::post-live', new Scope(slugs: true));
    $data = $item->data();
    $data['intro'] = 'Apex';
    $data['slug'] = 'post-apex';

    $item->write($data, 'Sweep: Acme → Apex', null);

    $entry = Entry::find('post-live');

    expect($entry->get('intro'))->toBe('Apex')
        ->and($entry->get('title'))->toBe('Live Acme')
        ->and($entry->slug())->toBe('post-apex')
        ->and($entry->data()->has('slug'))->toBeFalse();
});

it('finds nothing for a reference to something gone, or of a kind it does not know', function () {
    expect((new Items)->find('entry::missing'))->toBeNull()
        ->and((new Items)->find('nonsense'))->toBeNull()
        ->and(EntryItem::referenceFor('x'))->toBe('entry::x');
});
