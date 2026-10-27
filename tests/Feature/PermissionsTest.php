<?php

declare(strict_types=1);

use Bpmore\Sweep\Auth\Permissions;
use Bpmore\Sweep\ServiceProvider;
use Statamic\CP\Navigation\Nav as StatamicNav;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Role;
use Statamic\Facades\User;

/**
 * The four permissions, and the screen they open. Roles are real Statamic
 * roles checked through the real gate.
 */
beforeEach(function () {
    config(['statamic.editions.pro' => true]);   // more than one user

    $this->roles = [];
    $this->userWith = function (array $permissions) {
        $handle = 'tester-'.substr(md5(implode('|', $permissions)), 0, 8);
        $role = Role::make($handle)->addPermission('access cp');
        foreach ($permissions as $permission) {
            $role->addPermission($permission);
        }
        $role->save();
        $this->roles[] = $role;

        return User::make()->id('u-'.$handle)->email("$handle@example.com")->assignRole($role)->save();
    };
});

afterEach(function () {
    foreach ($this->roles as $role) {
        $role->delete();
    }
});

it('registers the four permissions in their own group, apply, undo and presets under use', function () {
    $group = Permission::boot()->tree()->firstWhere('handle', 'sweep');

    expect($group['label'])->toBe('Sweep')
        ->and(collect($group['permissions'])->pluck('value')->all())->toBe(['use sweep'])
        ->and(collect($group['permissions'][0]['children'])->pluck('value')->all())->toBe(['apply sweep', 'undo sweep', 'manage sweep presets']);
});

it('gives nobody anything by default, and a super admin everything', function () {
    $nobody = ($this->userWith)([]);
    $admin = User::make()->id('admin')->email('admin@example.com')->makeSuper()->save();

    foreach (['mayUse', 'mayApply', 'mayUndo', 'mayManagePresets'] as $check) {
        expect(Permissions::$check($nobody))->toBeFalse("$check for nobody")
            ->and(Permissions::$check($admin))->toBeTrue("$check for a super admin");
    }

    expect(Permissions::mayUse(null))->toBeFalse();
});

it('does not let a child permission work without use sweep', function () {
    // A role file edited by hand can hold a child without its parent.
    $orphan = ($this->userWith)([Permissions::APPLY, Permissions::UNDO, Permissions::MANAGE_PRESETS]);

    expect(Permissions::mayApply($orphan))->toBeFalse()
        ->and(Permissions::mayUndo($orphan))->toBeFalse()
        ->and(Permissions::mayManagePresets($orphan))->toBeFalse();

    $full = ($this->userWith)([Permissions::USE, Permissions::APPLY]);

    expect(Permissions::mayApply($full))->toBeTrue()
        ->and(Permissions::mayUndo($full))->toBeFalse();
});

it('opens the search screen with use sweep, and refuses it without', function () {
    $this->actingAs(($this->userWith)([]))
        ->get(cp_route('sweep.index'))
        ->assertForbidden();

    $this->actingAs(($this->userWith)([Permissions::USE]))
        ->get(cp_route('sweep.index'))
        ->assertOk();
});

it('lists Sweep under Tools only for people who may use it', function () {
    // Nav::build() comes back empty outside a real control panel request in
    // Testbench, so the extension is run against a fresh Nav and its base
    // items are read directly, as Lifecycle's test does.
    $sweepItems = function () {
        $real = new StatamicNav;
        Nav::swap($real);
        (new ReflectionMethod($provider = new ServiceProvider(app()), 'bootNavigation'))->invoke($provider);

        return collect((new ReflectionMethod($real, 'makeBaseItems'))->invoke($real))
            ->filter(fn ($item) => (string) $item->url() === cp_route('sweep.index'))
            ->values();
    };

    $this->actingAs(($this->userWith)([]));
    expect($sweepItems())->toBeEmpty();

    $this->actingAs(($this->userWith)([Permissions::USE]));
    expect($sweepItems())->toHaveCount(1)
        ->and($sweepItems()->first()->section())->toBe('Tools')
        ->and($sweepItems()->first()->display())->toBe('Sweep');
});
