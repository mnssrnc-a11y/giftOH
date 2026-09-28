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
        private NotificationService $notifications
    ) {}

    public function createRequest(array $data): array
    {
        return $this->fundingRequests->create($data);
    }

    public function requestDenialReason(string|int $userId): ?string
    {
        $cutoff = now()->subMonths(3);

        foreach ($this->getRequestsByUser($userId) as $request) {
            if (in_array($this->statusOf($request), ['pending', self::STATUS_UNDER_REVIEW], true)) {
                return 'The request is denied because you have an existing request or a previous request within the required three-month timeframe.';
            }

            if (! empty($request['created_at']) && Carbon::parse($request['created_at'])->gte($cutoff)) {
                return 'The request is denied because you have an existing request or a previous request within the required three-month timeframe.';
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