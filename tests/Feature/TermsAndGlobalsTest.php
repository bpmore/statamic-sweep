<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\FieldTree;
use Bpmore\Sweep\Content\Items;
use Bpmore\Sweep\Content\Scope;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

beforeEach(function () {
    useSites(['en', 'fr']);

    Taxonomy::make('tags')->sites(['en', 'fr'])->save();
    Taxonomy::make('topics')->sites(['en'])->save();
    Blueprint::make('tag')->setNamespace('taxonomies.tags')->setContents(['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'blurb', 'field' => ['type' => 'textarea']],
    ]])->save();

    Term::make()->taxonomy('tags')->slug('acme')
        ->dataForLocale('en', ['title' => 'Acme', 'blurb' => 'All about Acme'])
        ->dataForLocale('fr', ['title' => 'Acme FR', 'slug' => 'acme-fr'])
        ->save();
    Term::make()->taxonomy('topics')->slug('news')->dataForLocale('en', ['title' => 'News'])->save();

    GlobalSet::make('footer')->title('Footer')->sites(['en' => null, 'fr' => 'en'])->save();
    Blueprint::make('footer')->setNamespace('globals')->setContents(['fields' => [
        ['handle' => 'copyright', 'field' => ['type' => 'text', 'display' => 'Copyright']],
    ]])->save();
    GlobalSet::find('footer')->in('en')->data(['copyright' => '© Acme'])->save();
    GlobalSet::find('footer')->in('fr')->data(['copyright' => '© Acme SA'])->save();
    GlobalSet::make('header')->title('Header')->sites(['en' => null])->save();
});

function refs(Scope $scope): array
{
    $refs = array_map(fn ($item) => $item->reference(), iterator_to_array((new Items)->in($scope), false));
    sort($refs);

    return $refs;
}

it('lists each term once per site, narrowed by taxonomy and site', function () {
    $terms = new Scope(collections: [], globals: [], navs: [], containers: []);

    expect(refs($terms))->toBe(['term::tags::acme::en', 'term::tags::acme::fr', 'term::topics::news::en'])
        ->and(refs(new Scope(collections: [], taxonomies: ['tags'], globals: [], navs: [], containers: [], sites: ['fr'])))->toBe(['term::tags::acme::fr']);
});

it('gives the engine a term\'s values in its own site, and its slug only when asked', function () {
    $fr = (new Items)->find('term::tags::acme::fr');

    expect($fr->data())->toBe(['title' => 'Acme FR'])
        ->and($fr->site())->toBe('fr')
        ->and($fr->title())->toBe('Acme FR')
        ->and(array_map(fn ($f) => $f->handle, $fr->fields(new FieldTree)))->toContain('blurb')
        ->and((new Items)->findFor('term::tags::acme::fr', new Scope(slugs: true))->data()['slug'])->toBe('acme-fr')
        ->and((new Items)->findFor('term::tags::acme::en', new Scope(slugs: true))->data()['slug'])->toBe('acme');
});

it('writes a term in one site without touching the other', function () {
    $item = (new Items)->find('term::tags::acme::en');
    $item->write(['title' => 'Apex', 'blurb' => 'All about Acme'], 'Sweep', null);

    $term = Term::find('tags::acme');

    expect($term->in('en')->get('title'))->toBe('Apex')
        ->and($term->in('fr')->get('title'))->toBe('Acme FR')
        ->and($term->in('en')->get('blurb'))->toBe('All about Acme');
});

it('lists each global set once per site it has, narrowed by handle and site', function () {
    $globals = new Scope(collections: [], taxonomies: [], navs: [], containers: []);

    expect(refs($globals))->toBe(['global::footer::en', 'global::footer::fr', 'global::header::en'])
        ->and(refs(new Scope(collections: [], taxonomies: [], globals: ['footer'], navs: [], containers: [], sites: ['fr'])))->toBe(['global::footer::fr']);
});

it('reads and writes a global set in one site', function () {
    $fr = (new Items)->find('global::footer::fr');

    expect($fr->data())->toBe(['copyright' => '© Acme SA'])
        ->and($fr->title())->toBe('Footer')
        ->and(array_map(fn ($f) => $f->handle, $fr->fields(new FieldTree)))->toBe(['copyright']);

    $fr->write(['copyright' => '© Apex SA'], 'Sweep', null);

    expect(GlobalSet::find('footer')->in('fr')->get('copyright'))->toBe('© Apex SA')
        ->and(GlobalSet::find('footer')->in('en')->get('copyright'))->toBe('© Acme');
});

it('finds nothing for a term or global that is not there', function () {
    expect((new Items)->find('term::tags::gone::en'))->toBeNull()
        ->and((new Items)->find('global::gone::en'))->toBeNull()
        ->and((new Items)->find('global::header::fr'))->toBeNull();
});
