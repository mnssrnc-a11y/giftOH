<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{

    private const DEFAULT_ROLE = 'user';

    /** Roles the application recognises. Anything else is denied (fail closed). */
    private const KNOWN_ROLES = ['user', 'admin', 'super_admin'];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $account = $request->user();

        if ($account === null) {
            return redirect()->route('login');
        }

        $userRole = self::resolveRole($account);
        $allowedRoles = array_map(fn (string $role): string => self::normalizeRole($role), $roles);

        if ($userRole !== null && in_array($userRole, $allowedRoles, true)) {
            return $next($request);
        }

        Log::warning('Role access denied', [
            'user_id'     => $account->getAuthIdentifier(),
            'raw_role'    => $account->role ?? null,
            'resolved'    => $userRole,
            'required'    => $allowedRoles,
            'path'        => $request->path(),
        ]);

        // Send the person back to THEIR OWN workspace (never the one they tried to enter).
        // Each home route only admits its own role, so this cannot loop.
        return redirect()->route(self::homeRouteFor($userRole))->with(
            'alert_error',
            'You do not have permission to open that workspace.'
        );
    }

    public static function resolveRole(mixed $account): ?string
    {
        $raw = $account->role ?? null;

        if (! is_scalar($raw) || trim((string) $raw) === '') {
            return self::DEFAULT_ROLE;
        }

        $role = self::normalizeRole($raw);

        return in_array($role, self::KNOWN_ROLES, true) ? $role : null;
    }

    /** Named route each role should land on after sign-in. */
    public static function homeRouteFor(?string $role): string
    {
        return match ($role) {
            'admin'       => 'admin',
            'super_admin' => 'superadmin',
            'user'        => 'dashboarduser',
            default       => 'landing',
        };
    }

    private static function normalizeRole(mixed $role): string
    {
        return match (strtolower(trim((string) $role))) {
            'normal user', 'normal_user', 'regular user', 'regular_user', 'member', 'user' => 'user',
            'superadmin', 'super admin', 'super-admin', 'super_admin' => 'super_admin',
            'admin', 'administrator' => 'admin',
            default => strtolower(trim((string) $role)),
        };
    }
}