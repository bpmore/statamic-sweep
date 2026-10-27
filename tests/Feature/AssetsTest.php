<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\FieldTree;
use Bpmore\Sweep\Content\Items;
use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\Change;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Engine\Walker;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;

beforeEach(function () {
    Storage::fake('images');
    Storage::fake('docs');
    AssetContainer::make('images')->disk('images')->save();
    AssetContainer::make('docs')->disk('docs')->save();

    Blueprint::make('images')->setNamespace('assets')->setContents(['fields' => [
        ['handle' => 'alt', 'field' => ['type' => 'text', 'display' => 'Alt text']],
        ['handle' => 'credit', 'field' => ['type' => 'text', 'display' => 'Credit']],
        ['handle' => 'focus', 'field' => ['type' => 'text']],
    ]])->save();

    Storage::disk('images')->put('logo.png', 'png');
    Storage::disk('images')->put('team/photo.jpg', 'jpg');
    Storage::disk('docs')->put('acme.pdf', 'pdf');

    Asset::find('images::logo.png')->data(['alt' => 'Acme logo', 'credit' => 'Acme design team'])->save();
    Asset::find('images::team/photo.jpg')->data(['alt' => 'The team'])->save();
});

$assetsOnly = fn (?array $containers = null, array $sites = []) => new Scope(collections: [], taxonomies: [], globals: [], navs: [], containers: $containers, sites: $sites);

it('lists every asset in the containers in scope', function () use ($assetsOnly) {
    $refs = fn (Scope $scope) => array_map(fn ($i) => $i->reference(), iterator_to_array((new Items)->in($scope), false));

    expect($refs($assetsOnly()))->toBe(['asset::docs::acme.pdf', 'asset::images::logo.png', 'asset::images::team/photo.jpg'])
        ->and($refs($assetsOnly(['images'])))->toBe(['asset::images::logo.png', 'asset::images::team/photo.jpg']);
});

it('leaves assets out of a sweep scoped to some sites, because their metadata is shared by every site', function () use ($assetsOnly) {
    useSites(['en', 'fr']);

    expect(iterator_to_array((new Items)->in($assetsOnly(null, ['fr'])), false))->toBe([]);
});

it('searches the metadata fields and never the file name or path', function () {
    $item = (new Items)->find('asset::images::logo.png');
    $result = (new Walker(new Matcher(new Query('Acme', 'Apex'))))->walk($item->data(), $item->fields(new FieldTree));

    expect(array_map(fn (Change $c) => $c->key(), $result->changes))->toBe(['alt', 'credit'])
        ->and($item->site())->toBeNull()
        ->and($item->title())->toBe('logo.png')
        ->and((new Items)->find('asset::docs::acme.pdf')->data())->not->toContain('acme.pdf');
});

it('writes metadata without moving or renaming the file', function () {
    $item = (new Items)->find('asset::images::logo.png');
    $item->write(['alt' => 'Apex logo', 'credit' => 'Acme design team'], 'Sweep', null);

    $asset = Asset::find('images::logo.png');

    expect($asset->get('alt'))->toBe('Apex logo')
        ->and($asset->get('credit'))->toBe('Acme design team')
        ->and(Storage::disk('images')->exists('logo.png'))->toBeTrue();
});
