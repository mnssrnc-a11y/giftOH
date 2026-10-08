<?php

namespace App\Services;

use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseApprovalRepository;
use Carbon\Carbon;

class FundingService
{
    public const STATUS_UNDER_REVIEW = 'under review';

    private const STATUS_IDS = [
        'pending' => 1,
        'approved' => 2,
        'rejected' => 3,
        'completed' => 4,
        self::STATUS_UNDER_REVIEW => 5,
    ];

    public function __construct(
        private FirebaseFundingRepository $fundingRequests,
        private FirebaseApprovalRepository $approvals,
        private NotificationService $notifications,
        private SystemSettings $settings,
        private FundingRules $rules
    ) {}

    public function createRequest(array $data): array
    {
        return $this->fundingRequests->create($data);
    }

    public function requestDenialReason(string|int $userId): ?string
    {
        // Interval set by the super admin (System settings), at least 93 days.
        $days = $this->settings->requestIntervalDays();
        $cutoff = now()->subDays($days);

        foreach ($this->getRequestsByUser($userId) as $request) {
            if (in_array($this->statusOf($request), ['pending', self::STATUS_UNDER_REVIEW], true)) {
                return 'You already have a request in progress. Only one request can be open at a time.';
            }

            if (! empty($request['created_at']) && Carbon::parse($request['created_at'])->gte($cutoff)) {
                $next = Carbon::parse($request['created_at'])->addDays($days)->format('M d, Y');

                return "Requests must be at least {$days} days apart. You can submit a new request on {$next}.";
            }

            // Released funds must be liquidated (receipts + list of recipients) before asking again.
            if ($this->statusOf($request) === 'approved') {
                return 'Please submit the liquidation report (receipts and list of recipients) for your previous grant before requesting again.';
            }
        }

        return null;
    }

    public function getRequestById(string|int $id): ?array
    {
        return $this->fundingRequests->findById($id);
    }

    public function getRequestsByUser(string|int $userId): array
    {
        return $this->fundingRequests->findByUserId($userId);
    }

    public function getAllRequests(): array
    {
        return $this->fundingRequests->all();
    }

    /**
     * Normalise a request's status. The textual status wins over status_id because
     * older records kept status_id = 1 after being decided.
     */
    public function statusOf(array $request): string
    {
        $status = strtolower(trim((string) ($request['status_name'] ?? $request['status'] ?? '')));

        if ($status === '') {
            $status = array_search((int) ($request['status_id'] ?? 1), self::STATUS_IDS, true) ?: 'pending';
        }

        return match ($status) {
            'denied' => 'rejected',
            'canceled' => 'cancelled',
            default => $status,
        };
    }

    public function getPendingRequests(): array
    {
        return $this->requestsWithStatus('pending');
    }

    public function getApprovedRequests(): array
    {
        return $this->requestsWithStatus('approved');
    }

    /**
     * Requests an admin has reviewed that are waiting for a super admin's final decision.
     */
    public function getAwaitingFinalization(): array
    {
        return $this->requestsWithStatus(self::STATUS_UNDER_REVIEW);
    }

    /**
     * The amount actually granted: the finalized amount when set, otherwise the amount requested.
     */
    public function grantedAmountOf(array $request): float
    {
        return (float) ($request['approved_amount'] ?? $request['amount_requested'] ?? $request['amount'] ?? 0);
    }

    public function getTotalApprovedAmount(): float
    {
        $total = 0.0;
        foreach ($this->getAllRequests() as $request) {
            if (in_array($this->statusOf($request), ['approved', 'completed'], true)) {
                $total += $this->grantedAmountOf($request);
            }
        }

        return $total;
    }

    /**
     * Funds admins have recommended for release that a super admin has not finalized yet.
     */
    public function getReservedAmount(string|int|null $exceptRequestId = null): float
    {
        $total = 0.0;
        foreach ($this->getAwaitingFinalization() as $request) {
            if ((string) ($request['id'] ?? '') === (string) $exceptRequestId || ($request['admin_decision'] ?? '') !== 'approved') {
                continue;
            }
            $total += (float) ($request['admin_recommended_amount'] ?? 0);
        }

        return $total;
    }

    /**
     * First stage: an admin approves or rejects a pending request. The decision is recorded
     * and the request is handed to the super admin, who makes it final.
     */
    public function submitAdminReview(
        string|int $requestId,
        string $decision,
        string|int $adminId,
        ?float $recommendedAmount = null,
        ?string $notes = null,
        ?float $aiRecommendedAmount = null
    ): ?array {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== 'pending') {
            return null;
        }

        $timestamp = now()->toIso8601String();
        $recommendedAmount = $decision === 'approved' ? $recommendedAmount : null;
        $updated = $this->fundingRequests->update($requestId, [
            'status' => self::STATUS_UNDER_REVIEW,
            'status_name' => self::STATUS_UNDER_REVIEW,
            'status_id' => self::STATUS_IDS[self::STATUS_UNDER_REVIEW],
            'admin_decision' => $decision,
            'admin_recommended_amount' => $recommendedAmount,
            'ai_recommended_amount' => $aiRecommendedAmount,
            'admin_notes' => $notes,
            'admin_reviewed_by' => (string) $adminId,
            'admin_reviewed_at' => $timestamp,
        ]);

        if ($updated !== null) {
            $this->approvals->create([
                'request_id' => (string) $requestId,
                'stage' => 'admin_review',
                'approved_by' => (string) $adminId,
                'approval_status' => $decision,
                'recommended_amount' => $recommendedAmount,
                'approval_notes' => $notes,
                'decision_at' => $timestamp,
            ]);
        }

        return $updated;
    }

    /**
     * Second stage: a super admin confirms or overrides the admin's review.
     */
    public function finalizeRequest(
        string|int $requestId,
        string $decision,
        string|int $superAdminId,
        ?float $approvedAmount = null,
        ?string $notes = null
    ): ?array {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== self::STATUS_UNDER_REVIEW) {
            return null;
        }

        return $this->decideRequest($requestId, $decision, $superAdminId, $request['admin_notes'] ?? null, [
            'approved_amount' => $decision === 'approved' ? $approvedAmount : null,
            'finalized_by' => (string) $superAdminId,
            'final_notes' => $notes,
        ]);
    }

    public const MAX_APPEALS = 2;

    /**
     * The requester appeals a rejected request (at most twice) with a reason and an updated
     * supporting document. The request goes back to the admins for review; the earlier decision
     * stays in its history.
     */
    public function appeal(string|int $requestId, string|int $userId, string $reason, string $documentPath): ?array
    {
        $request = $this->getRequestById($requestId);
        $count = (int) ($request['appeals'] ?? 0);
        if ($request === null || (string) ($request['user_id'] ?? '') !== (string) $userId
            || $this->statusOf($request) !== 'rejected' || $count >= self::MAX_APPEALS) {
            return null;
        }

        $number = $count + 1;
        $timestamp = now()->toIso8601String();
        $history = array_values((array) ($request['appeal_history'] ?? []));
        $history[] = [
            'number' => $number,
            'at' => $timestamp,
            'reason' => $reason,
            'document' => $documentPath,
            'previous_decision_at' => $request['rejected_at'] ?? null,
            'previous_reason' => $request['final_notes'] ?? $request['admin_notes'] ?? null,
        ];

        $updated = $this->fundingRequests->update($requestId, [
            'status' => 'pending',
            'status_name' => 'pending',
            'status_id' => self::STATUS_IDS['pending'],
            'appeals' => $number,
            'appeal_history' => $history,
            'documents' => array_merge((array) ($request['documents'] ?? []), ["appeal_{$number}" => $documentPath]),
            // The admins review it again from the start of their decision.
            'admin_decision' => null,
            'admin_recommended_amount' => null,
            'ai_recommended_amount' => null,
            'admin_notes' => null,
            'admin_reviewed_by' => null,
            'admin_reviewed_at' => null,
            'approved_by' => null,
            'finalized_by' => null,
            'final_notes' => null,
            'rejected_at' => null,
        ]);

        if ($updated !== null) {
            $this->approvals->create([
                'request_id' => (string) $requestId,
                'stage' => 'appeal',
                'approved_by' => (string) $userId,
                'approval_status' => 'appealed',
                'approval_notes' => $reason,
                'decision_at' => $timestamp,
            ]);
        }

        return $updated;
    }

    /**
     * Where a request is in the foundation's process:
     * assessment → admin decision → super admin finalization → release → liquidation → completed.
     *
     * @return array{key: string, label: string, step: int}
     */
    public function stageOf(array $request): array
    {
        $assessment = (array) ($request['assessment'] ?? []);
        $liquidation = (array) ($request['liquidation'] ?? []);

        [$key, $label, $step] = match ($this->statusOf($request)) {
            'pending' => match (true) {
                ! empty($assessment['assessed_at']) && ($assessment['outcome'] ?? '') === 'recommended' => ['ready_for_decision', 'Assessed · awaiting admin decision', 2],
                ! empty($assessment['assessed_at']) => ['ready_for_decision', 'Assessed · not recommended', 2],
                ! empty($assessment['interview_at']) => ['interview_scheduled', 'Interview scheduled', 1],
                default => ['needs_assessment', 'Awaiting social worker assessment', 1],
            },
            self::STATUS_UNDER_REVIEW => ['awaiting_final', 'Awaiting final approval', 3],
            'approved' => match (true) {
                ($liquidation['status'] ?? '') === 'submitted' => ['liquidation_review', 'Liquidation under review', 5],
                ($liquidation['status'] ?? '') === 'returned' => ['liquidation_returned', 'Liquidation needs changes', 5],
                ! empty($request['disbursement']['released_at'] ?? null) => ['released', 'Funds released · liquidation due', 5],
                default => ['to_release', 'Approved · awaiting release', 4],
            },
            'completed' => ['completed', 'Completed', 6],
            'rejected' => ['rejected', 'Rejected', 0],
            default => ['other', ucfirst($this->statusOf($request)), 0],
        };

        return ['key' => $key, 'label' => $label, 'step' => $step];
    }

    public function assessmentDueAt(array $request): ?Carbon
    {
        return empty($request['created_at']) ? null : Carbon::parse($request['created_at'])->addDays((int) config('funding.assessment_days', 7));
    }

    public function liquidationDueAt(array $request): ?Carbon
    {
        $released = $request['disbursement']['released_at'] ?? null;

        return $released ? Carbon::parse($released)->addDays((int) config('funding.liquidation_days', 30)) : null;
    }

    /**
     * Social workers' interview: schedule (or reschedule) it.
     */
    public function scheduleInterview(string $requestId, array $interview, string|int $adminId): ?array
    {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== 'pending') {
            return null;
        }

        $assessment = array_merge((array) ($request['assessment'] ?? []), $interview, [
            'scheduled_by' => (string) $adminId,
            'scheduled_at' => now()->toIso8601String(),
            // A new schedule needs a new answer from the requester.
            'interview_response' => null,
        ]);
        $updated = $this->fundingRequests->update($requestId, ['assessment' => $assessment]);

        $when = Carbon::parse($interview['interview_at'])->format('M d, Y g:i A');
        $this->notifications->notify($request['user_id'] ?? '', $requestId, 'interview_scheduled', 'Interview scheduled',
            "A social worker will assess your request for {$this->orgOf($request)} on {$when} ({$interview['mode_label']}).");

        return $updated;
    }

    /**
     * Requester confirms the interview or asks for another schedule.
     *
     * @param  array{status: string, preferred_at: ?string, note: ?string}  $response
     */
    public function respondToInterview(string $requestId, array $response): ?array
    {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== 'pending' || empty($request['assessment']['interview_at'])
            || ! empty($request['assessment']['assessed_at'])) {
            return null;
        }

        $assessment = (array) $request['assessment'];
        $assessment['interview_response'] = array_merge($response, ['responded_at' => now()->toIso8601String()]);

        return $this->fundingRequests->update($requestId, ['assessment' => $assessment]);
    }

    /**
     * Admin verifies a submitted document or asks for it to be resubmitted.
     */
    public function reviewDocument(string $requestId, string $field, bool $verified, ?string $remarks, string|int $adminId): ?array
    {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== 'pending' || empty($request['documents'][$field] ?? null)) {
            return null;
        }

        $reviews = (array) ($request['document_reviews'] ?? []);
        $reviews[$field] = [
            'status' => $verified ? 'verified' : 'resubmit',
            'remarks' => $remarks,
            'reviewed_by' => (string) $adminId,
            'reviewed_at' => now()->toIso8601String(),
        ];

        return $this->fundingRequests->update($requestId, ['document_reviews' => $reviews]);
    }

    /**
     * Requester uploads a document that was missing or marked for resubmission.
     * Returns the replaced file path (so the caller can delete it) or false when not allowed.
     */
    public function replaceDocument(string $requestId, string $field, string $path): string|false|null
    {
        $request = $this->getRequestById($requestId);
        $status = $request['document_reviews'][$field]['status'] ?? null;
        $current = $request['documents'][$field] ?? null;
        if ($request === null || $this->statusOf($request) !== 'pending' || ($current && $status !== 'resubmit')) {
            return false;
        }

        $documents = (array) ($request['documents'] ?? []);
        $documents[$field] = $path;
        $reviews = (array) ($request['document_reviews'] ?? []);
        $reviews[$field] = ['status' => 'pending', 'resubmitted_at' => now()->toIso8601String()];
        $this->fundingRequests->update($requestId, ['documents' => $documents, 'document_reviews' => $reviews]);

        return $current;
    }

    /**
     * Review state of each required document: verified | resubmit | pending | missing.
     */
    public function documentStatuses(array $request): array
    {
        $statuses = [];
        foreach (array_keys($this->rules->requirementsFor($request['category_name'] ?? $request['category'] ?? null)) as $field) {
            $statuses[$field] = empty($request['documents'][$field] ?? null)
                ? 'missing'
                : ($request['document_reviews'][$field]['status'] ?? 'pending');
        }

        return $statuses;
    }

    public function documentsVerified(array $request): bool
    {
        $statuses = $this->documentStatuses($request);

        return $statuses !== [] && count(array_filter($statuses, static fn (string $status): bool => $status !== 'verified')) === 0;
    }

    /**
     * Social workers' assessment result. Approval requires a "recommended" outcome.
     */
    public function recordAssessment(string $requestId, array $result, string|int $adminId): ?array
    {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== 'pending') {
            return null;
        }

        $assessment = array_merge((array) ($request['assessment'] ?? []), $result, [
            'assessed_by' => (string) $adminId,
            'assessed_at' => now()->toIso8601String(),
        ]);

        $data = ['assessment' => $assessment];
        // A budget set before the assessment follows the number of people the social worker verified.
        if (! empty($request['budget']['per_person'])) {
            $data['amount_requested'] = round((float) $request['budget']['per_person'] * (int) $result['verified_beneficiaries'], 2);
            $data['budget'] = array_merge((array) $request['budget'], ['people' => (int) $result['verified_beneficiaries']]);
        }

        return $this->fundingRequests->update($requestId, $data);
    }

    /**
     * Admin decides the per-person budget. The amount is per person × the people verified by the
     * social worker (or listed in the request when not yet assessed).
     *
     * @param  array{items: array, per_person: float}  $budget  from FundingRules::buildBudget()
     */
    public function setBudget(string $requestId, array $budget, string|int $adminId): ?array
    {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== 'pending') {
            return null;
        }

        $people = (int) ($request['assessment']['verified_beneficiaries'] ?? $request['beneficiary_count'] ?? 0);

        return $this->fundingRequests->update($requestId, [
            'budget' => array_merge($budget, [
                'people' => $people,
                'set_by' => (string) $adminId,
                'set_at' => now()->toIso8601String(),
            ]),
            'amount_requested' => round($budget['per_person'] * $people, 2),
        ]);
    }

    /**
     * An admin's verification note on the request (staff only; never shown to the requester).
     */
    public function addStaffNote(string $requestId, string $body, \Illuminate\Contracts\Auth\Authenticatable $author): ?array
    {
        if ($this->getRequestById($requestId) === null || trim($body) === '') {
            return null;
        }

        $note = [
            'body' => mb_substr(trim($body), 0, 2000),
            'author_id' => (string) $author->getAuthIdentifier(),
            'author_name' => method_exists($author, 'fullName') ? ($author->fullName() ?: 'Admin') : 'Admin',
            'at' => now()->toIso8601String(),
        ];
        $note['id'] = $this->fundingRequests->pushChild($requestId, 'staff_notes', $note);

        return $note;
    }

    /**
     * Staff notes, oldest first.
     */
    public function staffNotesOf(array $request): array
    {
        $notes = array_values(array_filter((array) ($request['staff_notes'] ?? []), 'is_array'));
        usort($notes, static fn (array $a, array $b): int => strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? '')));

        return $notes;
    }

    /**
     * The money was sent by bank transfer to the receiver's account.
     */
    public function recordDisbursement(string $requestId, array $disbursement, string|int $adminId): ?array
    {
        $request = $this->getRequestById($requestId);
        if ($request === null || $this->statusOf($request) !== 'approved' || ! empty($request['disbursement']['released_at'] ?? null)) {
            return null;
        }

        $updated = $this->fundingRequests->update($requestId, [
            'disbursement' => array_merge((array) ($request['disbursement'] ?? []), $disbursement, [
                'recorded_by' => (string) $adminId,
                'recorded_at' => now()->toIso8601String(),
            ]),
        ]);

        $days = (int) config('funding.liquidation_days', 30);
        $this->notifications->notify($request['user_id'] ?? '', $requestId, 'funds_released', 'Funds released',
            '₱' . number_format((float) $disbursement['amount'], 2) . " for {$this->orgOf($request)} has been released. "
            . "Please submit the receipts and the list of recipients within {$days} days.");

        return $updated;
    }

    /**
     * The organization reports how the money was used.
     */
    public function submitLiquidation(string $requestId, array $report): ?array
    {
        $request = $this->getRequestById($requestId);
        $current = (array) ($request['liquidation'] ?? []);
        if ($request === null || $this->statusOf($request) !== 'approved'
            || empty($request['disbursement']['released_at'] ?? null)
            || ($current['status'] ?? null) === 'submitted') {
            return null;
        }

        $history = (array) ($current['history'] ?? []);
        if ($current !== []) {
            $history[] = array_diff_key($current, ['history' => true]);
        }

        return $this->fundingRequests->update($requestId, [
            'liquidation' => array_merge($report, [
                'status' => 'submitted',
                'submitted_at' => now()->toIso8601String(),
                'history' => $history,
            ]),
        ]);
    }

    /**
     * Admin verifies the liquidation (request becomes completed) or returns it for corrections.
     */
    public function reviewLiquidation(string $requestId, bool $verified, ?string $remarks, string|int $adminId): ?array
    {
        $request = $this->getRequestById($requestId);
        if ($request === null || ($request['liquidation']['status'] ?? null) !== 'submitted') {
            return null;
        }

        $liquidation = array_merge((array) $request['liquidation'], [
            'status' => $verified ? 'verified' : 'returned',
            'review_remarks' => $remarks,
            'reviewed_by' => (string) $adminId,
            'reviewed_at' => now()->toIso8601String(),
        ]);

        $data = ['liquidation' => $liquidation];
        if ($verified) {
            $data += ['status' => 'completed', 'status_name' => 'completed', 'status_id' => self::STATUS_IDS['completed'], 'completed_at' => now()->toIso8601String()];
        }
        $updated = $this->fundingRequests->update($requestId, $data);

        $this->notifications->notify($request['user_id'] ?? '', $requestId,
            $verified ? 'liquidation_verified' : 'liquidation_returned',
            $verified ? 'Liquidation verified' : 'Liquidation needs changes',
            $verified
                ? "Thank you! The liquidation for {$this->orgOf($request)} is verified and the request is complete."
                : "Your liquidation for {$this->orgOf($request)} was returned: " . ($remarks ?: 'please review and resubmit.'));

        return $updated;
    }

    /**
     * Every stored file path recorded on a request: requirements, legacy uploads,
     * proof of release, and current and earlier liquidation files.
     */
    public function filesOf(array $request): array
    {
        $liquidations = array_merge([(array) ($request['liquidation'] ?? [])], (array) ($request['liquidation']['history'] ?? []));
        $paths = array_merge(
            array_values((array) ($request['documents'] ?? [])),
            [$request['doc_image'] ?? null, $request['id_image'] ?? null, $request['financial_rprt'] ?? null, $request['barangay_clr'] ?? null],
            [$request['disbursement']['proof'] ?? null],
        );
        foreach ($liquidations as $liquidation) {
            $paths = array_merge($paths, (array) ($liquidation['receipts'] ?? []), (array) ($liquidation['photos'] ?? []), [$liquidation['recipient_list'] ?? null]);
        }

        return array_values(array_unique(array_filter($paths, static fn ($path): bool => is_string($path) && $path !== '')));
    }

    private function orgOf(array $request): string
    {
        return (string) ($request['org_name'] ?? 'your organization');
    }

    private function requestsWithStatus(string $status): array
    {
        return array_values(array_filter(
            $this->fundingRequests->all(),
            fn (array $request): bool => $this->statusOf($request) === $status
        ));
    }

    public function decideRequest(
        string|int $requestId,
        string $decision,
        string|int $approverId,
        ?string $notes = null,
        array $extra = []
    ): ?array {
        $request = $this->getRequestById($requestId);
        if ($request === null) {
            return null;
        }

        $timestamp = now()->toIso8601String();
        $updated = $this->fundingRequests->update($requestId, array_merge([
            'status' => $decision,
            'status_name' => $decision,
            'status_id' => self::STATUS_IDS[$decision] ?? null,
            'approved_by' => (string) $approverId,
            'admin_notes' => $notes,
            $decision === 'approved' ? 'approved_at' : 'rejected_at' => $timestamp,
        ], $extra));

        if ($updated === null) {
            return null;
        }

        $this->approvals->create([
            'request_id' => (string) $requestId,
            'stage' => $extra === [] ? 'direct' : 'final',
            'approved_by' => (string) $approverId,
            'approval_status' => $decision,
            'approval_notes' => $notes,
            'decision_at' => $timestamp,
        ]);

        $this->notifications->createFundingDecision(
            $request['user_id'] ?? '',
            $requestId,
            $decision,
            $request['org_name'] ?? 'your organization'
        );

        return $updated;
    }

    public function getNotificationsByUser(string|int $userId): array
    {
        $notifications = [];

        foreach ($this->getRequestsByUser($userId) as $request) {
            $statusId = (int) ($request['status_id'] ?? 0);
            $status = strtolower((string) ($request['status_name'] ?? $request['status'] ?? ''));
            $isApproved = $statusId === 2 || $status === 'approved';
            $isRejected = $statusId === 3 || in_array($status, ['rejected', 'denied'], true);

            if (! $isApproved && ! $isRejected) {
                continue;
            }

            $label = $isApproved ? 'Fund Request Approved' : 'Fund Request Denied';
            $organization = $request['org_name'] ?? 'your organization';

            $notifications[] = [
                'label' => $label,
                'message' => "Your fund request for {$organization} was " . ($isApproved ? 'approved.' : 'denied.'),
                'type' => $isApproved ? 'approved' : 'denied',
                'created_at' => $request['updated_at'] ?? $request['created_at'] ?? null,
            ];
        }

        usort($notifications, static function (array $first, array $second): int {
            return strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? ''));
        });

        return $notifications;
    }
}