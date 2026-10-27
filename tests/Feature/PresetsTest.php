<?php

declare(strict_types=1);

use Bpmore\Sweep\Auth\Permissions;
use Bpmore\Sweep\Engine\InvalidQuery;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Presets\Presets;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Role;
use Statamic\Facades\User;

beforeEach(function () {
    config(['statamic.editions.pro' => true, 'sweep.presets_path' => $this->presetPath = storage_path('framework/testing/presets-'.uniqid())]);
});

afterEach(fn () => File::deleteDirectory($this->presetPath));

function runPreset(string $handle, string $subject, array $edits = []): string
{
    $preset = [...app(Presets::class)->find($handle), ...$edits];
    $matcher = new Matcher(Query::fromArray($preset));

    return Matcher::apply($subject, $matcher->find($subject));
}

it('ships the three presets from the spec', function () {
    expect(array_column(app(Presets::class)->all(), 'handle'))->toBe(['domain-change', 'rebrand', 'wordpress-shortcodes'])
        ->and(array_column(app(Presets::class)->all(), 'built_in'))->toBe([true, true, true]);
});

it('changes the domain only at a URL\'s start', function () {
    expect(runPreset('domain-change', 'See http://old.example.com/a and HTTPS://old.example.com/b, not http://bold.example.com.'))
        ->toBe('See https://www.example.com/a and https://www.example.com/b, not http://bold.example.com.')
        ->and(runPreset('domain-change', 'http://old.example.community/x'))->toBe('http://old.example.community/x');
});

it('removes WordPress shortcodes and keeps what is between them', function () {
    $before = '[caption id="attachment_5" align="alignleft" width="300"]<img src="a.jpg"> A photo[/caption] Text [embed]https://youtu.be/x[/embed] and [gallery ids="1,2"] [contact-form]';

    expect(runPreset('wordpress-shortcodes', $before))
        ->toBe('<img src="a.jpg"> A photo Text https://youtu.be/x and  [contact-form]');
});

it('rebrands as whole words, keeping case', function () {
    expect(runPreset('rebrand', 'Acme, ACME and acme; not Acmeville.'))->toBe('Apex, APEX and apex; not Acmeville.');
});

it('saves a site preset as YAML, and lets it replace a built-in of the same name', function () {
    $presets = app(Presets::class);

    $handle = $presets->save(['title' => 'Old brand', 'find' => 'Foo', 'replace' => 'Bar', 'mode' => 'word']);

    expect($handle)->toBe('old-brand')
        ->and(File::get($this->presetPath.'/old-brand.yaml'))->toContain('find: Foo')
        ->and($presets->find('old-brand'))->toMatchArray(['title' => 'Old brand', 'mode' => 'word', 'built_in' => false]);

    $presets->save(['title' => 'Rebrand', 'find' => 'Mine', 'replace' => 'Ours']);

    expect($presets->find('rebrand'))->toMatchArray(['find' => 'Mine', 'built_in' => false])
        ->and(count($presets->all()))->toBe(4);
});

it('refuses a preset whose search would not run, or that is not YAML', function () {
    expect(fn () => app(Presets::class)->save(['title' => 'Bad', 'find' => '(x', 'mode' => 'regex']))->toThrow(InvalidQuery::class, 'regular expression')
        ->and(fn () => app(Presets::class)->save(['find' => 'x']))->toThrow(InvalidQuery::class, 'title')
        ->and(fn () => Presets::parse('title: [unclosed'))->toThrow(InvalidQuery::class, 'not valid YAML')
        ->and(fn () => Presets::parse('just a string'))->toThrow(InvalidQuery::class, 'not a preset');
});

it('round-trips a preset through its YAML', function () {
    $preset = app(Presets::class)->find('wordpress-shortcodes');

    expect(Presets::parse(Presets::dump($preset)))->toBe(array_intersect_key($preset, Presets::parse(Presets::dump($preset))));
});

it('deletes only site presets', function () {
    app(Presets::class)->save(['title' => 'Mine', 'find' => 'a']);

    expect(app(Presets::class)->delete('mine'))->toBeTrue()
        ->and(app(Presets::class)->delete('rebrand'))->toBeFalse()
        ->and(app(Presets::class)->delete('../../x'))->toBeFalse()
        ->and(app(Presets::class)->find('rebrand'))->not->toBeNull();
});

describe('in the control panel', function () {
    beforeEach(function () {
        $this->roles = [];
        $this->person = function (array $permissions) {
            $role = Role::make('r-'.md5(implode($permissions)))->addPermission('access cp');
            foreach ($permissions as $permission) {
                $role->addPermission($permission);
            }
            $role->save();
            $this->roles[] = $role;

            return User::make()->id('u-'.md5(implode($permissions)))->email(md5(implode($permissions)).'@example.com')->assignRole($role)->save();
        };
    });

    afterEach(function () {
        foreach ($this->roles as $role) {
            $role->delete();
        }
    });

    it('lists presets for everyone with Sweep, and lets them download one', function () {
        $this->actingAs(($this->person)([Permissions::USE]));

        $this->get(cp_route('sweep.presets'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('sweep/Presets')->count('presets', 3)->where('mayManage', false));

        $this->get(cp_route('sweep.presets.export', 'rebrand'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="sweep-preset-rebrand.yaml"')
            ->assertSee('preserve_case: true', false);

        $this->get(cp_route('sweep.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->count('presets', 3)->where('mayManagePresets', false));
    });

    it('needs manage sweep presets to save, import or delete', function () {
        $this->actingAs(($this->person)([Permissions::USE]));

        $this->post(cp_route('sweep.presets.store'), ['title' => 'X', 'find' => 'a'])->assertForbidden();

        $this->actingAs(($this->person)([Permissions::USE, Permissions::MANAGE_PRESETS]));

        $this->post(cp_route('sweep.presets.store'), ['title' => 'From the form', 'find' => 'a', 'replace' => 'b', 'mode' => 'literal', 'case_sensitive' => true])->assertRedirect();
        $this->post(cp_route('sweep.presets.store'), ['yaml' => "title: Imported\nfind: x\nreplace: y\n"])->assertRedirect();
        $this->post(cp_route('sweep.presets.store'), ['yaml' => "title: Broken\nfind: '(x'\nmode: regex\n"])->assertSessionHasErrors('yaml');
        $this->post(cp_route('sweep.presets.store'), ['find' => 'a'])->assertSessionHasErrors('title');

        expect(array_column(app(Presets::class)->all(), 'handle'))->toContain('from-the-form', 'imported')->not->toContain('broken');

        $this->delete(cp_route('sweep.presets.destroy', 'imported'))->assertRedirect();
        $this->delete(cp_route('sweep.presets.destroy', 'rebrand'))->assertNotFound();

        expect(app(Presets::class)->find('imported'))->toBeNull();
    });
});
