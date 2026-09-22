<?php

namespace App\Support;

use App\Http\Middleware\EnsureRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Maps an account's role to the Blade layout it should extend.
 *
 * This is for views that are genuinely shared across roles and need to pick
 * their own layout at render time (see usage below). It deliberately reuses
 * EnsureRole::resolveRole() — the same role-normalizing logic already
 * enforced at the route/middleware level — so the layout a person sees can
 * never disagree with which routes they're actually allowed to reach. Do
 * not duplicate the role matching here; if the mapping in EnsureRole
 * changes, this stays correct automatically.
 */
class Layout
{
    public static function forRole(?Authenticatable $account = null): string
    {
        $role = EnsureRole::resolveRole($account ?? Auth::user());

        return match ($role) {
            'admin' => 'layouts.admin',
            'super_admin' => 'layouts.spAdmin',
            // 'user', and any unresolved/unknown role, gets the least-
            // privileged layout — never fall through to an admin shell.
            default => 'layouts.dashboard',
        };
    }
}