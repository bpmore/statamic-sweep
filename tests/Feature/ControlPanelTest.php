<?php

declare(strict_types=1);

use Bpmore\Sweep\Auth\Permissions;
use Bpmore\Sweep\Sweeps\Status;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;

beforeEach(function () {
    config(['statamic.editions.pro' => true, 'sweep.path' => $this->sweepPath = storage_path('framework/testing/sweep-'.uniqid())]);

    Collection::make('blog')->title('Blog')->save();
    Blueprint::make('post')->setNamespace('collections.blog')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']]]])->save();
    Entry::make()->id('b1')->collection('blog')->slug('b1')->data(['title' => 'Acme Acme'])->save();
    Entry::make()->id('b2')->collection('blog')->slug('b2')->data(['title' => 'Acme'])->save();

    $this->roles = [];
    $this->person = function (string $id, array $permissions) {
        $role = Role::make("r-{$id}")->addPermission('access cp')->addPermission('view blog entries')->addPermission('edit blog entries');
        foreach ($permissions as $permission) {
            $role->addPermission($permission);
        }
        $role->save();
        $this->roles[] = $role;

        return User::make()->id($id)->email("{$id}@example.com")->assignRole($role)->save();
    };
});

afterEach(function () {
    foreach ($this->roles as $role) {
        $role->delete();
    }
    File::deleteDirectory($this->sweepPath);
});

function startSweep(array $overrides = [])
{
    return test()->post(cp_route('sweep.sweeps.store'), [
        'find' => 'Acme', 'replace' => 'Apex', 'mode' => 'literal', 'case_sensitive' => true,
        'collections' => ['blog'], 'taxonomies' => [], 'globals' => [], 'navs' => [], 'containers' => [],
        ...$overrides,
    ]);
}

it('shows the search screen with the site\'s collections', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]))
        ->get(cp_route('sweep.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sweep/Search')
            ->where('options.collections', [['value' => 'blog', 'label' => 'Blog']])
            ->where('options.sites', []));
});

it('starts a sweep and opens it', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]));

    $response = startSweep();
    $sweep = app(SweepStore::class)->all()[0];

    $response->assertRedirect(cp_route('sweep.sweeps.show', $sweep->id));

    expect($sweep->status)->toBe(Status::Previewed)
        ->and($sweep->createdBy['id'])->toBe('sam')
        ->and($sweep->scope->collections)->toBe(['blog'])
        ->and($sweep->scope->taxonomies)->toBe([]);
});

it('refuses a bad search with a message on the field', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]));

    startSweep(['find' => ''])->assertSessionHasErrors('find');
    startSweep(['find' => '(abc', 'mode' => 'regex'])->assertSessionHasErrors(['find' => 'The regular expression does not work: missing closing parenthesis at offset 4. Check the pattern and try again.']);
    startSweep(['collections' => []])->assertSessionHasErrors('collections');
    startSweep(['mode' => 'glob'])->assertSessionHasErrors('mode');

    expect(app(SweepStore::class)->all())->toBe([]);
});

it('needs use sweep to search at all', function () {
    $this->actingAs(($this->person)('nobody', []));

    $this->get(cp_route('sweep.index'))->assertForbidden();
    startSweep()->assertForbidden();
    $this->get(cp_route('sweep.history'))->assertForbidden();
});

it('shows a sweep\'s results, a page at a time, to the person who started it', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]));
    startSweep();
    $sweep = app(SweepStore::class)->all()[0];

    $this->get(cp_route('sweep.sweeps.show', $sweep->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sweep/Sweep')
            ->where('sweep.status', 'previewed')
            ->where('sweep.tally.matches', 3)
            ->where('items.total', 2)
            ->where('items.data.0.title', 'Acme Acme')
            ->where('items.data.0.changes.0.label', 'Title')
            ->where('items.data.0.changes.0.hits.1', ['before' => 'Acme ', 'text' => 'Acme', 'replacement' => 'Apex', 'after' => '', 'selected' => true])
            ->where('mayApply', false));
});

it('keeps a sweep from anyone but its starter and super admins', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]));
    startSweep();
    $sweep = app(SweepStore::class)->all()[0];

    $this->actingAs(($this->person)('kim', [Permissions::USE, Permissions::APPLY, Permissions::UNDO]));
    $this->get(cp_route('sweep.sweeps.show', $sweep->id))->assertForbidden();
    $this->post(cp_route('sweep.sweeps.apply', $sweep->id))->assertForbidden();

    $this->actingAs(User::make()->id('admin')->email('admin@example.com')->makeSuper()->save());
    $this->get(cp_route('sweep.sweeps.show', $sweep->id))->assertOk();

    $this->get(cp_route('sweep.sweeps.show', '01j00000000000000000000000'))->assertNotFound();
    $this->get(cp_route('sweep.sweeps.show', '..%2F..%2F.env'))->assertNotFound();
});

it('lets the starter leave out a match or an item before applying', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE, Permissions::APPLY]));
    startSweep();
    $sweep = app(SweepStore::class)->all()[0];

    $this->patch(cp_route('sweep.sweeps.select', $sweep->id), ['item' => 0, 'change' => 0, 'hit' => 1, 'selected' => false])->assertRedirect();
    $this->patch(cp_route('sweep.sweeps.select', $sweep->id), ['item' => 1, 'selected' => false])->assertRedirect();
    $this->patch(cp_route('sweep.sweeps.select', $sweep->id), ['item' => 99, 'selected' => false])->assertRedirect();
    $this->patch(cp_route('sweep.sweeps.select', $sweep->id), ['item' => 0, 'change' => 0, 'selected' => false])->assertSessionHasErrors('hit');

    $this->post(cp_route('sweep.sweeps.apply', $sweep->id))->assertRedirect();

    expect(Entry::find('b1')->get('title'))->toBe('Apex Acme')
        ->and(Entry::find('b2')->get('title'))->toBe('Acme')
        ->and(app(SweepStore::class)->find($sweep->id)->status)->toBe(Status::Applied);

    // No more choosing once it is applied.
    $this->patch(cp_route('sweep.sweeps.select', $sweep->id), ['item' => 1, 'selected' => true])->assertSessionHas('error');
    expect(app(SweepStore::class)->readChunk($sweep->id, 1)[1]['selected'])->toBeFalse();
});

it('needs apply sweep to apply, and undo sweep to undo', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]));
    startSweep();
    $sweep = app(SweepStore::class)->all()[0];

    $this->post(cp_route('sweep.sweeps.apply', $sweep->id))->assertForbidden();
    expect(Entry::find('b2')->get('title'))->toBe('Acme');

    $this->actingAs(($this->person)('sam2', [Permissions::USE, Permissions::APPLY]));
    $mine = startSweep();
    $sweep = app(SweepStore::class)->all()[0];
    $this->post(cp_route('sweep.sweeps.apply', $sweep->id))->assertRedirect();
    expect(Entry::find('b2')->get('title'))->toBe('Apex');

    $this->post(cp_route('sweep.sweeps.undo', $sweep->id))->assertForbidden();
    expect(Entry::find('b2')->get('title'))->toBe('Apex');
});

it('undoes from the control panel for someone with undo sweep', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE, Permissions::APPLY, Permissions::UNDO]));
    startSweep();
    $sweep = app(SweepStore::class)->all()[0];
    $this->post(cp_route('sweep.sweeps.apply', $sweep->id));

    $this->get(cp_route('sweep.sweeps.show', $sweep->id))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sweep.undoable', true)->where('mayUndo', true)->where('mayApply', false));

    $this->post(cp_route('sweep.sweeps.undo', $sweep->id))->assertRedirect();

    expect(Entry::find('b2')->get('title'))->toBe('Acme')
        ->and(app(SweepStore::class)->find($sweep->id)->status)->toBe(Status::Undone);

    $this->post(cp_route('sweep.sweeps.undo', $sweep->id))->assertSessionHas('error');
});

it('lists every sweep in the history, and says which can be opened', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]));
    startSweep();
    $this->actingAs(($this->person)('kim', [Permissions::USE]));
    startSweep(['find' => 'Other']);

    $this->get(cp_route('sweep.history'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sweep/History')
            ->count('sweeps', 2)
            ->where('sweeps.0.find', 'Other')
            ->where('sweeps.0.may_open', true)
            ->where('sweeps.1.find', 'Acme')
            ->where('sweeps.1.may_open', false)
            ->where('sweeps.1.created_by', 'sam@example.com')
            ->where('sweeps.1.scope', 'collections: blog'));
});

it('fills the search screen in again from an earlier sweep', function () {
    $this->actingAs(($this->person)('sam', [Permissions::USE]));
    startSweep(['find' => '(\d+)', 'replace' => '#$1', 'mode' => 'regex', 'handles' => 'title, intro']);
    $sweep = app(SweepStore::class)->all()[0];

    $this->get(cp_route('sweep.sweeps.again', $sweep->id))->assertRedirect(cp_route('sweep.index'));

    $this->get(cp_route('sweep.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('initial.find', '(\d+)')
            ->where('initial.replace', '#$1')
            ->where('initial.mode', 'regex')
            ->where('initial.collections', ['blog'])
            ->where('initial.handles', 'title, intro'));
});
