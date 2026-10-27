<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Http\Controllers;

use Bpmore\Sweep\Auth\Permissions;
use Bpmore\Sweep\Http\Presenters\SweepPresenter;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Http\Request;
use Inertia\Response;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Every sweep, newest first: who, when, find, replace, scope, counts, status.
 */
class HistoryController extends CpController
{
    private const PER_PAGE = 50;

    public function index(Request $request, SweepStore $store, SweepPresenter $presenter): Response
    {
        $user = User::current();
        abort_unless(Permissions::mayUse($user), 403);

        $all = $store->all();
        $pages = max(1, (int) ceil(count($all) / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $pages);

        $sweeps = array_map(fn ($sweep) => [
            ...$presenter->summary($sweep),
            'may_open' => Permissions::mayOpen($user, $sweep->createdBy),
            'again_url' => cp_route('sweep.sweeps.again', $sweep->id),
        ], array_slice($all, ($page - 1) * self::PER_PAGE, self::PER_PAGE));

        return inertia('sweep/History', [
            'sweeps' => $sweeps,
            'page' => $page,
            'pages' => $pages,
            'searchUrl' => cp_route('sweep.index'),
        ]);
    }
}
