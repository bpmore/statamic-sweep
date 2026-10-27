<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Auth;

use Statamic\Contracts\Auth\User;

/**
 * The four permissions, and the one place each is asked about, so a screen,
 * a route and a job cannot disagree.
 *
 *   use sweep             search and preview; results hold only items the
 *                         person may edit
 *   apply sweep           write a previewed sweep to the site
 *   undo sweep            put an applied sweep back
 *   manage sweep presets  create, edit and delete saved searches
 *
 * The last three are children of the first in the roles screen: applying,
 * undoing or managing presets without being able to search makes no sense.
 * Each is still checked together with `use sweep`, because a role file
 * edited by hand can hold a child without its parent.
 *
 * Super admins pass every check through Statamic itself. Nobody else has any
 * of these until a role is given them.
 */
final class Permissions
{
    public const USE = 'use sweep';

    public const APPLY = 'apply sweep';

    public const UNDO = 'undo sweep';

    public const MANAGE_PRESETS = 'manage sweep presets';

    public static function mayUse(?User $user): bool
    {
        return $user !== null && (bool) $user->can(self::USE);
    }

    public static function mayApply(?User $user): bool
    {
        return self::mayUse($user) && (bool) $user->can(self::APPLY);
    }

    public static function mayUndo(?User $user): bool
    {
        return self::mayUse($user) && (bool) $user->can(self::UNDO);
    }

    public static function mayManagePresets(?User $user): bool
    {
        return self::mayUse($user) && (bool) $user->can(self::MANAGE_PRESETS);
    }
}
