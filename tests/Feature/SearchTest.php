<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\Fingerprint;
use Bpmore\Sweep\Content\Item;
use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Content\Search;
use Bpmore\Sweep\Content\Tally;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Engine\Result;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;

beforeEach(function () {
    config(['statamic.editions.pro' => true]);

    Collection::make('blog')->save();
    Collection::make('legal')->save();
    Blueprint::make('post')->setNamespace('collections.blog')->setContents(['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'body', 'field' => ['type' => 'bard']],
        ['handle' => 'seo', 'field' => ['type' => 'seo_title']],
    ]])->save();

    Entry::make()->id('b1')->collection('blog')->slug('b1')->data(['title' => 'Acme news', 'body' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ac', 'marks' => [['type' => 'bold']]], ['type' => 'text', 'text' => 'me']]],
    ], 'seo' => 'Acme'])->save();
    Entry::make()->id('b2')->collection('blog')->slug('b2')->data(['title' => 'Nothing here'])->save();
    Entry::make()->id('l1')->collection('legal')->slug('l1')->data(['title' => 'Acme terms'])->save();

    $role = Role::make('blogger')->addPermission('access cp')->addPermission('view blog entries')->addPermission('edit blog entries')->addPermission('use sweep');
    $role->save();
    $this->blogger = User::make()->id('blogger')->email('blogger@example.com')->assignRole($role)->save();
});

afterEach(fn () => Role::find('blogger')?->delete());

/** @return array{0: array<string, array{0: Item, 1: Result, 2: string}>, 1: Tally} */
function search(string $find, ?Statamic\Contracts\Auth\User $user, ?Scope $scope = null): array
{
    $found = [];
    $tally = app(Search::class)->run(new Query($find, 'Apex'), $scope ?? new Scope, $user, function (Item $item, Result $result, string $fingerprint) use (&$found) {
        $found[$item->reference()] = [$item, $result, $fingerprint];
    });

    return [$found, $tally];
}

it('never shows a person entries in a collection they cannot edit: acceptance 6', function () {
    [$found, $tally] = search('Acme', $this->blogger);

    expect(array_keys($found))->toBe(['entry::b1'])
        ->and($tally->skippedForPermission)->toBe(1)
        ->and($tally->searched)->toBe(2);
});

it('searches everything in scope when there is no person, as from Artisan', function () {
    [$found, $tally] = search('Acme', null);

    expect(array_keys($found))->toEqualCanonicalizing(['entry::b1', 'entry::l1'])
        ->and($tally->skippedForPermission)->toBe(0);
});

it('counts items matched, matches and split matches', function () {
    [, $tally] = search('Acme', null);

    expect($tally->toArray())->toMatchArray([
        'searched' => 3,
        'matched_items' => 2,
        'matches' => 2,
        'splits' => 1,
        'skipped_for_permission' => 0,
    ]);
});

it('hands over the item\'s fingerprint as it was searched', function () {
    [$found] = search('Acme', null);
    [$item, , $fingerprint] = $found['entry::b1'];

    expect($fingerprint)->toBe(Fingerprint::of($item->data()));
});

it('searches a fieldtype named in config, and not otherwise', function () {
    [$found] = search('Acme', null, new Scope(collections: ['blog']));
    expect(array_map(fn ($c) => $c->key(), $found['entry::b1'][1]->changes))->toBe(['title']);

    config(['sweep.fieldtypes' => ['seo_title']]);

    [$found] = search('Acme', null, new Scope(collections: ['blog']));
    expect(array_map(fn ($c) => $c->key(), $found['entry::b1'][1]->changes))->toBe(['title', 'seo']);
});
