<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\ApprovalVerificationCode;
use App\Repositories\FirebaseAdminPostRepository;
use App\Services\AdminDashboardService;
use App\Services\FundAllocationAdvisor;
use App\Services\FundingService;
use App\Services\IotService;
use App\Services\VerificationService;

class AdminController extends Controller
{
    public function __construct(
        private FundingService $fundingService,
        private IotService $iotService,
        private AdminDashboardService $dashboard,
        private FundAllocationAdvisor $advisor,
        private FirebaseAdminPostRepository $posts,
        private VerificationService $verificationService
    ) {
    }

    public function admin()
    {
        if (! Auth::user()->isAdmin()) {
            return redirect()->route('login');
        }

        $iotMetrics = $this->iotService->getDashboardMetrics();
        $data = $this->dashboard->build($iotMetrics);
        $pendingCount = $data['fundingSummary']['pendingCount'];
        $totalFundRequests = count($data['fundingRequests']);

        return view('adminPage.admin', array_merge($iotMetrics, $data, compact('pendingCount', 'totalFundRequests')));
    }

    /**
     * AI-assisted recommendation for how much of a request can be granted.
     */
    public function recommendation(string $id): JsonResponse
    {
        $recommendation = $this->advisor->recommend($id);
        abort_if($recommendation === null, 404);

        return response()->json($recommendation);
    }

    /**
     * Admin approves or rejects a pending request. The decision is forwarded to the
     * super admin for finalization, after email verification when the admin has 2FA enabled.
     */
    public function initiateApprovalAction(Request $request, string $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:approved,rejected',
            'amount' => 'required_if:action,approved|nullable|numeric|min:1',
            'ai_amount' => 'nullable|numeric|min:0',
            'notes' => 'required_if:action,rejected|nullable|string|max:1000',
        ], [
            'amount.required_if' => 'Enter the amount to recommend for release.',
            'notes.required_if' => 'Add a note explaining why the request is rejected.',
        ]);

        $fundingRequest = $this->fundingService->getRequestById($id);
        if ($fundingRequest === null || $this->fundingService->statusOf($fundingRequest) !== 'pending') {
            return $this->toSection('funding')->with('alert_error', 'This request is no longer awaiting admin review.');
        }

        $requested = (float) ($fundingRequest['amount_requested'] ?? $fundingRequest['amount'] ?? 0);
        if ($validated['action'] === 'approved' && (float) $validated['amount'] > $requested) {
            return back()->with('alert_error', 'The recommended amount cannot be more than the amount requested.');
        }

        $pending = [
            'request_id' => $id,
            'action' => $validated['action'],
            'amount' => $validated['action'] === 'approved' ? (float) $validated['amount'] : null,
            'ai_amount' => isset($validated['ai_amount']) ? (float) $validated['ai_amount'] : null,
            'notes' => $validated['notes'] ?? null,
        ];

        $user = Auth::user();
        if (! filter_var($user->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return $this->submitReview($pending);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verificationService->store($user->email, $code, 'approval_verification_codes');
        Mail::to($user->email)->send(new ApprovalVerificationCode($code, $user->fname ?? 'Admin', $validated['action']));
        session(['pending_approval' => $pending]);

        return redirect()->route('admin.fund-request.verify.form')
            ->with('status', 'A 6-digit approval verification code has been sent to your email.');
    }

    public function showApprovalVerifyForm()
    {
        if (! session()->has('pending_approval')) {
            return redirect()->route('admin')->with('alert_error', 'No pending approval found.');
        }

        return view('adminPage.admin-approval-verify');
    }

    /**
     * Record the admin review and hand the request to the super admin.
     */
    private function submitReview(array $pending)
    {
        $updated = $this->fundingService->submitAdminReview(
            $pending['request_id'],
            $pending['action'],
            Auth::id(),
            $pending['amount'] ?? null,
            $pending['notes'] ?? null,
            $pending['ai_amount'] ?? null
        );

        if ($updated === null) {
            return $this->toSection('funding')->with('alert_error', 'This request is no longer awaiting admin review.');
        }

        $verb = $pending['action'] === 'approved' ? 'Approval' : 'Rejection';

        return $this->toSection('funding')
            ->with('status', "{$verb} recorded and sent to the super admin for finalization.");
    }

    public function storePost(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:10000',
            'type' => 'required|in:announcement,funding_update,compiled_report',
            'audience' => 'required|in:' . FirebaseAdminPostRepository::AUDIENCE_PUBLIC . ',' . FirebaseAdminPostRepository::AUDIENCE_USERS,
        ]);

        $this->posts->create(array_merge($validated, [
            'author_id' => (string) Auth::id(),
            'author_role' => 'admin',
        ]));

        $where = $validated['audience'] === FirebaseAdminPostRepository::AUDIENCE_PUBLIC
            ? 'on the landing page and user pages'
            : 'on signed-in users\' pages';

        return $this->toSection('updates')->with('status', "Update posted. It now appears {$where}.");
    }

    public function destroyPost(string $id)
    {
        $post = $this->posts->findById($id);
        abort_if($post === null, 404);
        abort_unless((string) ($post['author_id'] ?? '') === (string) Auth::id(), 403, 'You can only delete your own updates.');

        $this->posts->delete($id);

        return $this->toSection('updates')->with('status', 'Update deleted.');
    }

    /**
     * Redirect to a tab of the single-page admin workspace.
     */
    public static function toSection(string $section)
    {
        return redirect()->to(route('admin') . '#' . $section);
    }
}
