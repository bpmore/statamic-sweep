<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Http\Controllers;

use Bpmore\Sweep\Auth\Permissions;
use Bpmore\Sweep\Http\Presenters\SweepPresenter;
use Bpmore\Sweep\Sweeps\Status;
use Bpmore\Sweep\Sweeps\Sweep;
use Bpmore\Sweep\Sweeps\Sweeps;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Response;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * One sweep: its progress, its results, and applying and undoing it.
 */
class SweepController extends CpController
{
    public function show(Request $request, string $sweep, SweepStore $store, SweepPresenter $presenter): Response
    {
        $sweep = $this->open($store, $sweep);
        $user = User::current();

        return inertia('sweep/Sweep', [
            'sweep' => $presenter->summary($sweep),
            'items' => $presenter->page($sweep, (int) $request->query('page', 1)),
            'mayApply' => Permissions::mayApply($user) && $sweep->status === Status::Previewed,
            'mayUndo' => Permissions::mayUndo($user),
            'urls' => [
                'select' => cp_route('sweep.sweeps.select', $sweep->id),
                'apply' => cp_route('sweep.sweeps.apply', $sweep->id),
                'undo' => cp_route('sweep.sweeps.undo', $sweep->id),
                'again' => cp_route('sweep.sweeps.again', $sweep->id),
                'search' => cp_route('sweep.index'),
                'history' => cp_route('sweep.history'),
            ],
        ]);
    }

    /**
     * Keeps or leaves out one item, or one match in it, while the sweep is a
     * preview.
     */
    public function select(Request $request, string $sweep, SweepStore $store): RedirectResponse|HttpResponse
    {
        $sweep = $this->open($store, $sweep);

        $values = $request->validate([
            'item' => ['required', 'integer', 'min:0'],
            'change' => ['nullable', 'integer', 'min:0'],
            'hit' => ['nullable', 'integer', 'min:0', 'required_with:change'],
            'selected' => ['required', 'boolean'],
        ]);

        if ($sweep->status !== Status::Previewed) {
            return $request->expectsJson()
                ? response(['message' => __('sweep::messages.error_not_a_preview')], 409)
                : back()->with('error', __('sweep::messages.error_not_a_preview'));
        }

        $item = (int) $values['item'];
        $chunk = intdiv($item, SweepStore::CHUNK) + 1;
        $offset = $item % SweepStore::CHUNK;
        $selected = (bool) $values['selected'];

        $store->updateChunk($sweep->id, $chunk, function (array $records) use ($offset, $values, $selected): array {
            if (! isset($records[$offset])) {
                return $records;
            }

            if (isset($values['change'])) {
                $hit = &$records[$offset]['changes'][(int) $values['change']]['hits'][(int) $values['hit']];

                if (is_array($hit)) {
                    $hit['selected'] = $selected;
                }
                unset($hit);

                // Choosing a match in an item left out brings the item back.
                if ($selected) {
                    $records[$offset]['selected'] = true;
                }
            } else {
                $records[$offset]['selected'] = $selected;
            }

            return $records;
        });

        // The results page saves choices in the background, so it is not
        // sent a page it would only throw away.
        return $request->expectsJson() ? response()->noContent() : back();
    }

    public function apply(string $sweep, SweepStore $store, Sweeps $sweeps): RedirectResponse
    {
        $sweep = $this->open($store, $sweep);
        $user = User::current();
        abort_unless(Permissions::mayApply($user), 403);

        if (! $sweeps->apply($sweep, $user)) {
            return back()->with('error', __('sweep::messages.error_not_a_preview'));
        }

        return back();
    }

    public function undo(string $sweep, SweepStore $store, Sweeps $sweeps): RedirectResponse
    {
        $sweep = $this->open($store, $sweep);
        $user = User::current();
        abort_unless(Permissions::mayUndo($user), 403);

        if (! $sweeps->undo($sweep, $user)) {
            return back()->with('error', __('sweep::messages.error_not_undoable'));
        }

        return back();
    }

    /** "Search again": the search screen, filled in with this sweep's search. */
    public function again(string $sweep, SweepStore $store): RedirectResponse
    {
        abort_unless(Permissions::mayUse(User::current()), 403);
        $sweep = $store->find($sweep) ?? abort(404);

        return redirect(cp_route('sweep.index'))->with('sweep.initial', [
            ...$sweep->query->toArray(),
            ...$sweep->scope->toArray(),
            'handles' => implode(', ', $sweep->scope->handles),
        ]);
    }

    private function open(SweepStore $store, string $id): Sweep
    {
        $sweep = $store->find($id) ?? abort(404);

        abort_unless(Permissions::mayOpen(User::current(), $sweep->createdBy), 403);

        return $sweep;
    }
}
