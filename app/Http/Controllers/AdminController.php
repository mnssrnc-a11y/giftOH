<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\ApprovalVerificationCode;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use App\Repositories\FirebaseAdminPostRepository;
use App\Repositories\FirebaseDonationRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\AdminDashboardService;
use App\Services\Ai\AiClient;
use App\Services\FundAllocationAdvisor;
use App\Services\FundingService;
use App\Services\IotService;
use App\Services\NotificationService;
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
        $aiProviders = app(AiClient::class)->status();
        $prices = app(\App\Services\PriceListService::class);
        $priceGroups = $prices->grouped();
        $priceRun = $prices->lastRun();

        return view('adminPage.admin', array_merge($iotMetrics, $data, compact('pendingCount', 'totalFundRequests', 'aiProviders', 'priceGroups', 'priceRun')));
    }

    /**
     * Donations ledger: recorded donations plus each smart box's running total, from Firebase.
     */
    public function donations(FirebaseDonationRepository $donationRepository, FirebaseUserRepository $users)
    {
        try {
            $records = $donationRepository->all();
        } catch (\Throwable) {
            $records = null;
        }

        $donations = collect($records ?? [])->map(function (array $donation) use ($users): array {
            $donor = ! empty($donation['user_id']) ? ($users->findById($donation['user_id']) ?? []) : [];

            return [
                'id' => (string) ($donation['id'] ?? ''),
                'date' => $donation['created_at'] ?? $donation['donated_at'] ?? $donation['detected_at'] ?? null,
                'amount' => (float) ($donation['amount'] ?? 0),
                'box' => $donation['iot_box_id'] ?? $donation['box_id'] ?? null,
                'type' => $donation['type'] ?? $donation['payment_method'] ?? $donation['method'] ?? 'Donation',
                'status' => strtolower((string) ($donation['status'] ?? 'verified')),
                'donor' => trim(($donor['fname'] ?? '') . ' ' . ($donor['lname'] ?? '')) ?: ($donation['donor_name'] ?? null),
            ];
        })->sortByDesc('date')->values()->all();

        $boxes = collect($this->iotService->getBoxes() ?? [])->map(fn (array $box): array => [
            'id' => $box['id'],
            'location' => $box['location'] ?? '—',
            'total' => (float) ($box['total'] ?? 0),
            'coins' => (int) ($box['totalCoins'] ?? 0),
            'status' => $box['status'] ?? null,
        ])->sortBy('id')->values()->all();

        return view('adminPage.donations', [
            'donations' => $donations,
            'donationsAvailable' => $records !== null,
            'boxes' => $boxes,
            'boxTotal' => array_sum(array_column($boxes, 'total')),
            'directTotal' => array_sum(array_column($donations, 'amount')),
            'totalFunds' => $this->iotService->getTotalFunds(),
            'availableFunds' => $this->iotService->getAvailableFunds(),
            'pendingCount' => count($this->fundingService->getPendingRequests()),
        ]);
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

        // Social workers interview and assess every request before it can be approved.
        $assessment = (array) ($fundingRequest['assessment'] ?? []);
        if ($validated['action'] === 'approved' && (empty($assessment['assessed_at']) || ($assessment['outcome'] ?? '') !== 'recommended')) {
            return redirect()->route('admin.fund-request.show', $id)
                ->with('alert_error', 'A request can be approved only after the social worker\'s assessment recommends it.');
        }
        if ($validated['action'] === 'approved' && ! $this->fundingService->documentsVerified($fundingRequest)) {
            return redirect()->route('admin.fund-request.show', $id)
                ->with('alert_error', 'Verify every required document before approving.');
        }
        if ($validated['action'] === 'approved' && empty($fundingRequest['budget']['per_person'])) {
            return redirect()->route('admin.fund-request.show', $id)
                ->with('alert_error', 'Set the budget per person before approving.');
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
        try {
            Mail::to($user->email)->send(new ApprovalVerificationCode($code, $user->fname ?? 'Admin', $validated['action']));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->with('alert_error', 'We could not email your approval code. Try again later, or turn off email verification in Settings to submit without a code.');
        }
        $this->verificationService->store($user->email, $code, 'approval_verification_codes');
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
        app(\App\Services\AuditLogger::class)->record('funding', "Admin recommended {$pending['action']}"
            . ($pending['amount'] ? ' of ₱' . number_format($pending['amount'], 2) : ''), $pending['request_id']);
        app(NotificationService::class)->notifySuperAdmins($pending['request_id'], 'awaiting_final', 'Request ready for your final decision',
            (Auth::user()->fullName() ?: 'An admin') . ' recommends ' . ($pending['action'] === 'approved'
                ? 'approving ₱' . number_format((float) $pending['amount'], 2)
                : 'rejecting') . ' the request from ' . ($updated['org_name'] ?? 'an organization') . '.');

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
            // Optional photo shown with the update (scanned for malware like every upload).
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'image.image' => 'The photo must be a JPG, PNG or WebP image.',
            'image.max' => 'The photo must not be larger than 5 MB.',
        ]);

        $image = $request->hasFile('image') ? $request->file('image')->store('announcements', 'public') : null;
        $this->posts->create(array_merge(collect($validated)->except('image')->all(), [
            'image' => $image ?: null,
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
        if (! empty($post['image'])) {
            Storage::disk('public')->delete($post['image']);
        }

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
