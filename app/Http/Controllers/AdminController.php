<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\FirebaseUserRepository;
use App\Services\FundingService;

class AdminController extends Controller
{
    public function __construct(
        private FundingService $fundingService,
        private FirebaseUserRepository $firebaseUsers
    ) {
    }

    public function admin()
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->route('login');
        }

        $pendingRequests = $this->pendingRequestsForView();

        return view('adminPage.admin', compact('pendingRequests'));
    }

    public function adminApproval()
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->route('login');
        }

        $pendingRequests = $this->pendingRequestsForView();

        return view('adminPage.admin-approval-verify', compact('pendingRequests'));
    }

    public function adminFundApprove(Request $request, string $id)
    {
        return $this->updateFundingRequest($request, $id, 'approved');
    }

    public function adminFundReject(Request $request, string $id)
    {
        return $this->updateFundingRequest($request, $id, 'rejected');
    }

    private function updateFundingRequest(Request $request, string $id, string $decision)
    {
        $request->validate(['notes' => 'nullable|string']);

        $updated = $this->fundingService->decideRequest(
            $id,
            $decision,
            Auth::id(),
            $request->input('notes')
        );

        abort_if($updated === null, 404);

        return redirect()->route('admin')->with('status', 'Funding request updated successfully.');
    }

    private function pendingRequestsForView()
    {
        $requests = array_map(function (array $request): object {
            $user = $this->firebaseUsers->findById($request['user_id'] ?? '') ?? [];

            return (object) array_merge($request, [
                'title' => $request['title'] ?? $request['org_name'] ?? 'Funding request',
                'description' => $request['description'] ?? $request['mission'] ?? '',
                'amount_requested' => $request['amount_requested'] ?? $request['amount'] ?? 0,
                'user' => (object) $user,
                'category' => (object) [
                    'category_name' => $request['category_name'] ?? $request['category'] ?? 'General',
                ],
            ]);
        }, $this->fundingService->getPendingRequests());

        usort($requests, static fn (object $first, object $second): int =>
            (float) ($second->ai_score ?? 0) <=> (float) ($first->ai_score ?? 0)
        );

        return collect($requests);
    }
}
