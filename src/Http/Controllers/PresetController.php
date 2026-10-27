<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Http\Controllers;

use Bpmore\Sweep\Auth\Permissions;
use Bpmore\Sweep\Engine\InvalidQuery;
use Bpmore\Sweep\Presets\Presets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Saved searches: everyone with Sweep can use and download them; only
 * `manage sweep presets` can add, import or delete.
 */
class PresetController extends CpController
{
    public function index(Presets $presets): Response
    {
        abort_unless(Permissions::mayUse(User::current()), 403);

        return inertia('sweep/Presets', [
            'presets' => array_map(fn (array $preset) => [
                ...$preset,
                'yaml' => Presets::dump($preset),
                'export_url' => cp_route('sweep.presets.export', $preset['handle']),
                'delete_url' => $preset['built_in'] ? null : cp_route('sweep.presets.destroy', $preset['handle']),
            ], $presets->all()),
            'mayManage' => Permissions::mayManagePresets(User::current()),
            'storeUrl' => cp_route('sweep.presets.store'),
            'searchUrl' => cp_route('sweep.index'),
        ]);
    }

    /**
     * Saves a preset, from the search screen's fields or from pasted YAML.
     */
    public function store(Request $request, Presets $presets): RedirectResponse
    {
        abort_unless(Permissions::mayManagePresets(User::current()), 403);

        try {
            $handle = $request->filled('yaml')
                ? $presets->save(Presets::parse((string) $request->input('yaml')))
                : $presets->save($request->only(['title', 'description', 'find', 'replace', 'mode', 'case_sensitive', 'preserve_case']));
        } catch (InvalidQuery $e) {
            throw ValidationException::withMessages([$request->filled('yaml') ? 'yaml' : 'title' => $e->getMessage()]);
        }

        return back()->with('success', __('sweep::messages.preset_saved', ['handle' => $handle]));
    }

    public function destroy(string $preset, Presets $presets): RedirectResponse
    {
        abort_unless(Permissions::mayManagePresets(User::current()), 403);

        $presets->delete($preset) || abort(404);

        return back();
    }

    /** The preset as a YAML file, to send to another site. */
    public function export(string $preset, Presets $presets): HttpResponse
    {
        abort_unless(Permissions::mayUse(User::current()), 403);

        $found = $presets->find($preset) ?? abort(404);

        return response(Presets::dump($found), 200, [
            'Content-Type' => 'application/x-yaml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sweep-preset-'.$found['handle'].'.yaml"',
        ]);
    }
}
