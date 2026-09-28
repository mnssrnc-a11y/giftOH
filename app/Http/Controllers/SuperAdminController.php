<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AdminDashboardService;
use App\Services\FundingService;
use App\Services\IotService;

class SuperAdminController extends Controller
{
    public function __construct(
        private FundingService $fundingService,
        private AdminDashboardService $dashboard,
        private IotService $iotService
    ) {
    }

    public function index()
    {
        // Only requests an admin has already reviewed reach the super admin.
        $fundRequests = array_values(array_filter(
            $this->dashboard->fundingRows(),
            static fn (array $row): bool => $row['review'] !== []
        ));
        $availableFunds = $this->iotService->getAvailableFunds();

        return view('supperAdminPage.spAd_dashB', compact('fundRequests', 'availableFunds'));
    }

    public function finalize(Request $request, string $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'amount' => 'required_if:decision,approved|nullable|numeric|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $fundingRequest = $this->fundingService->getRequestById($id);
        $requested = (float) ($fundingRequest['amount_requested'] ?? $fundingRequest['amount'] ?? 0);
        if ($validated['decision'] === 'approved' && (float) $validated['amount'] > $requested) {
            return redirect()->to(route('superadmin') . '#requests')
                ->with('alert_error', 'The approved amount cannot be more than the amount requested.');
        }

        $updated = $this->fundingService->finalizeRequest(
            $id,
            $validated['decision'],
            Auth::id(),
            $validated['decision'] === 'approved' ? (float) $validated['amount'] : null,
            $validated['notes'] ?? null
        );

        if ($updated === null) {
            return redirect()->to(route('superadmin') . '#requests')
                ->with('alert_error', 'This request is not awaiting finalization.');
        }

        return redirect()->to(route('superadmin') . '#requests')
            ->with('status', 'Request ' . ($validated['decision'] === 'approved' ? 'approved' : 'rejected') . ' and the requester has been notified.');
    }
}
