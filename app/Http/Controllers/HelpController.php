<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureRole;
use App\Repositories\FirebaseUserRepository;
use App\Services\FundingService;
use App\Support\Tutorials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Help & tour: how the system works for each role, and a hands-on tutorial for every page.
 */
class HelpController extends Controller
{
    public function show(FundingService $funding)
    {
        $role = EnsureRole::resolveRole(Auth::user()) ?? 'user';
        $tutorials = Tutorials::forRole($role);

        // Tutorials of a request page open on the newest request.
        $latest = null;
        if (collect($tutorials)->contains('record', true)) {
            try {
                $requests = $role === 'user' ? $funding->getRequestsByUser(Auth::id()) : $funding->getAllRequests();
                if ($role === 'admin') {
                    // Cancelled requests are no longer the admins' to open.
                    $requests = array_filter($requests, fn (array $r): bool => $funding->statusOf($r) !== 'cancelled');
                }
                $latest = collect($requests)->sortByDesc(fn ($r) => $r['created_at'] ?? '')->first()['id'] ?? null;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // The role's own pages first (starting with "Getting started"), then the pages every role has.
        $list = collect($tutorials)->sortBy(fn (array $t) => count($t['roles']) > 1)->map(fn (array $t) => [
            'title' => $t['title'],
            'about' => $t['about'],
            'url' => match (true) {
                ! $t['record'] => route($t['route'], ['tutorial' => $t['id']]) . ($t['section'] ? "#{$t['section']}" : ''),
                $latest !== null => route($t['route'], ['id' => $latest, 'tutorial' => $t['id']]),
                default => null,
            },
            'done' => in_array($t['id'], Tutorials::seen(Auth::user()), true),
        ])->values()->all();

        return view('pages.help', ['role' => $role, 'tutorials' => $list]);
    }

    /** A tutorial was finished or skipped: it no longer starts by itself for this account. */
    public function completeTour(Request $request, FirebaseUserRepository $users)
    {
        $id = $request->validate(['id' => ['required', 'string', Rule::in(array_keys(config('tour.tutorials', [])))]])['id'];
        $seen = Tutorials::seen(Auth::user());

        if (! in_array($id, $seen, true)) {
            $users->update(Auth::id(), ['tutorials_seen' => [...$seen, $id]]);
        }

        return response()->noContent();
    }
}
