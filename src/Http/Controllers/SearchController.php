<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Http\Controllers;

use Bpmore\Sweep\Auth\Permissions;
use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\InvalidQuery;
use Bpmore\Sweep\Engine\Mode;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Sweeps\Sweeps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * The search screen, where every sweep starts.
 */
class SearchController extends CpController
{
    public function index(Request $request): Response
    {
        abort_unless(Permissions::mayUse(User::current()), 403);

        $options = fn ($items, callable $label) => $items
            ->map(fn ($item) => ['value' => $item->handle(), 'label' => (string) $label($item)])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return inertia('sweep/Search', [
            'options' => [
                'collections' => $options(Collection::all(), fn ($c) => $c->title()),
                'taxonomies' => $options(Taxonomy::all(), fn ($t) => $t->title()),
                'globals' => $options(GlobalSet::all(), fn ($g) => $g->title()),
                'navs' => $options(Nav::all(), fn ($n) => $n->title()),
                'containers' => $options(AssetContainer::all(), fn ($a) => $a->title()),
                'sites' => Site::multiEnabled() ? Site::all()->map(fn ($s) => ['value' => $s->handle(), 'label' => $s->name()])->values()->all() : [],
                'statuses' => array_map(fn (string $s) => ['value' => $s, 'label' => __("sweep::messages.status_entry_{$s}")], Scope::STATUSES),
            ],
            // A search carried over from "Search again" in the history, or a preset.
            'initial' => $request->session()->get('sweep.initial'),
            'startUrl' => cp_route('sweep.sweeps.store'),
            'historyUrl' => cp_route('sweep.history'),
        ]);
    }

    public function store(Request $request, Sweeps $sweeps): RedirectResponse
    {
        $user = User::current();
        abort_unless(Permissions::mayUse($user), 403);

        $values = $request->validate([
            'find' => ['required', 'string', 'max:2000'],
            'replace' => ['nullable', 'string', 'max:2000'],
            'mode' => ['required', Rule::enum(Mode::class)],
            'case_sensitive' => ['boolean'],
            'preserve_case' => ['boolean'],
            'collections' => ['nullable', 'array'],
            'taxonomies' => ['nullable', 'array'],
            'globals' => ['nullable', 'array'],
            'navs' => ['nullable', 'array'],
            'containers' => ['nullable', 'array'],
            'sites' => ['array'],
            'statuses' => ['array'],
            'statuses.*' => [Rule::in(Scope::STATUSES)],
            'handles' => ['nullable', 'string', 'max:1000'],
            'slugs' => ['boolean'],
            'code' => ['boolean'],
        ]);

        try {
            $query = Query::fromArray($values);
        } catch (InvalidQuery $e) {
            throw ValidationException::withMessages(['find' => $e->getMessage()]);
        }

        $list = static fn (string $key): array => array_values(array_map('strval', (array) ($values[$key] ?? [])));

        $scope = new Scope(
            collections: $list('collections'),
            taxonomies: $list('taxonomies'),
            globals: $list('globals'),
            navs: $list('navs'),
            containers: $list('containers'),
            sites: $list('sites'),
            statuses: $list('statuses'),
            handles: array_values(array_filter(array_map('trim', explode(',', (string) ($values['handles'] ?? ''))))),
            slugs: (bool) ($values['slugs'] ?? false),
            code: (bool) ($values['code'] ?? false),
        );

        if (array_filter(array_map(fn (string $kind) => $scope->{$kind}, Scope::KINDS)) === []) {
            throw ValidationException::withMessages(['collections' => __('sweep::messages.error_nothing_in_scope')]);
        }

        $sweep = $sweeps->start($query, $scope, $user);

        return redirect(cp_route('sweep.sweeps.show', $sweep->id));
    }
}
