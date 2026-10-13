<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureRole;
use App\Repositories\FirebaseUserRepository;
use Illuminate\Support\Facades\Auth;

/**
 * Help & tour: how the system works for each role, and the guided tour for new users.
 */
class HelpController extends Controller
{
    public function show()
    {
        $role = EnsureRole::resolveRole(Auth::user()) ?? 'user';

        return view('pages.help', [
            'role' => $role,
            'tourUrl' => route(EnsureRole::homeRouteFor($role), ['tour' => 1]),
        ]);
    }

    /** The tour was finished or skipped: it no longer starts by itself for this account. */
    public function completeTour(FirebaseUserRepository $users)
    {
        $users->update(Auth::id(), ['tour_version' => (int) config('tour.version')]);

        return response()->noContent();
    }
}
