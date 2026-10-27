<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Http\Controllers;

use Bpmore\Sweep\Auth\Permissions;
use Inertia\Response;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * The search screen: where every sweep starts.
 */
class SearchController extends CpController
{
    public function index(): Response
    {
        abort_unless(Permissions::mayUse(User::current()), 403);

        return inertia('sweep/Search', [
            'mayApply' => Permissions::mayApply(User::current()),
        ]);
    }
}
