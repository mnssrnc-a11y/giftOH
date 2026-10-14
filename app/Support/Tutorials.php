<?php

namespace App\Support;

use App\Http\Middleware\EnsureRole;

/**
 * The hands-on tutorials of config/tour.php: which ones a role can take, which ones run on the page
 * being shown, and which ones an account has already finished.
 */
class Tutorials
{
    /** @return array<string, array> id => tutorial, for this role (user, admin, super_admin). */
    public static function forRole(string $role): array
    {
        return collect(config('tour.tutorials', []))
            ->filter(fn (array $tutorial) => in_array($role, $tutorial['roles'] ?? [], true))
            ->map(fn (array $tutorial, string $id) => $tutorial + ['id' => $id, 'section' => null, 'record' => false])
            ->all();
    }

    /** The tutorials of the current page (several on a workspace page: one per section). */
    public static function forCurrentPage(string $role): array
    {
        return array_values(array_filter(self::forRole($role), fn (array $tutorial) => request()->routeIs($tutorial['route'])));
    }

    /** The tutorial on the role's home page, started by ?tour=1. */
    public static function welcomeFor(string $role): ?string
    {
        $home = EnsureRole::homeRouteFor($role);

        return collect(self::forRole($role))
            ->first(fn (array $tutorial) => $tutorial['route'] === $home && in_array($tutorial['section'], [null, 'dashboard'], true))['id'] ?? null;
    }

    /** Tutorials this account finished or skipped. */
    public static function seen($user): array
    {
        return array_values(array_filter((array) ($user?->tutorials_seen ?? []), 'is_string'));
    }
}
