<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\FieldTree;
use Bpmore\Sweep\Content\Items;
use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\Change;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Engine\Walker;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Nav;

beforeEach(function () {
    useSites(['en', 'fr']);

    Nav::make('main')->title('Main nav')->maxDepth(3)->save();
    Blueprint::make('main')->setNamespace('navigation')->setContents(['fields' => [
        ['handle' => 'note', 'field' => ['type' => 'text', 'display' => 'Note']],
        ['handle' => 'icon', 'field' => ['type' => 'select']],
    ]])->save();

    $this->tree = [
        ['id' => 'b1', 'title' => 'Acme home', 'url' => 'https://staging.test/Acme/', 'data' => ['note' => 'Acme note', 'icon' => 'acme'], 'children' => [
            ['id' => 'b2', 'entry' => 'acme-entry', 'title' => 'About Acme', 'children' => [
                ['id' => 'b3', 'title' => 'Deep Acme', 'children' => [
                    ['id' => 'b4', 'title' => 'Too deep Acme'],
                ]],
            ]],
        ]],
    ];

    Nav::find('main')->makeTree('en', $this->tree)->save();
    Nav::find('main')->makeTree('fr', [['id' => 'f1', 'title' => 'Acme FR']])->save();
    Nav::make('footer')->title('Footer')->save();
});

function navKeys(string $reference): array
{
    $item = (new Items)->find($reference);
    $result = (new Walker(new Matcher(new Query('Acme', 'Apex'))))->walk($item->data(), $item->fields(new FieldTree));

    return array_map(fn (Change $c) => $c->key(), $result->changes);
}

it('lists each nav once per site it has a tree in, narrowed by handle and site', function () {
    $navs = new Scope(collections: [], taxonomies: [], globals: [], containers: []);
    $refs = array_map(fn ($i) => $i->reference(), iterator_to_array((new Items)->in($navs), false));

    expect($refs)->toBe(['nav::main::en', 'nav::main::fr'])
        ->and(array_map(fn ($i) => $i->reference(), iterator_to_array((new Items)->in(new Scope(collections: [], taxonomies: [], globals: [], containers: [], navs: ['main'], sites: ['fr'])), false)))
        ->toBe(['nav::main::fr']);
});

it('searches branch titles, URLs and blueprint fields, to the nav\'s depth, and never ids or entries', function () {
    expect(navKeys('nav::main::en'))->toBe([
        'tree.0.title',
        'tree.0.url',
        'tree.0.data.note',
        'tree.0.children.0.title',
        'tree.0.children.0.children.0.title',
    ]);
});

it('writes the tree back with only the changed strings different', function () {
    $item = (new Items)->find('nav::main::en');
    $data = $item->data();
    $data['tree'][0]['children'][0]['title'] = 'About Apex';

    $item->write($data, 'Sweep', null);

    $expected = $this->tree;
    $expected[0]['children'][0]['title'] = 'About Apex';

    expect(Nav::find('main')->in('en')->tree())->toBe($expected)
        ->and(Nav::find('main')->in('fr')->tree())->toBe([['id' => 'f1', 'title' => 'Acme FR']]);
});

it('names the nav and the site', function () {
    $item = (new Items)->find('nav::main::fr');

    expect($item->title())->toBe('Main nav')
        ->and($item->site())->toBe('fr')
        ->and($item->kind())->toBe('nav')
        ->and((new Items)->find('nav::gone::en'))->toBeNull();
});
