<?php

namespace App\Services;

use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseApprovalRepository;
use Carbon\Carbon;

class FundingService
{
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
            $status = strtolower((string) ($request['status_name'] ?? $request['status'] ?? ''));
            if ((int) ($request['status_id'] ?? 0) === 1 || $status === 'pending') {
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

    public function getPendingRequests(): array
    {
        return array_values(array_filter(
            $this->fundingRequests->all(),
            static fn (array $request): bool => strtolower((string) ($request['status_name'] ?? $request['status'] ?? 'pending')) === 'pending'
                || (int) ($request['status_id'] ?? 0) === 1
        ));
    }

    public function getApprovedRequests(): array
    {
        return array_values(array_filter(
            $this->fundingRequests->all(),
            static fn (array $request): bool => strtolower((string) ($request['status_name'] ?? $request['status'] ?? '')) === 'approved'
                || (int) ($request['status_id'] ?? 0) === 2
        ));
    }

    public function getTotalApprovedAmount(): float
    {
        $total = 0.0;
        foreach ($this->getApprovedRequests() as $request) {
            $total += (float) ($request['amount_requested'] ?? $request['amount'] ?? 0);
        }

        return $total;
    }

    public function decideRequest(
        string|int $requestId,
        string $decision,
        string|int $approverId,
        ?string $notes = null
    ): ?array {
        $request = $this->getRequestById($requestId);
        if ($request === null) {
            return null;
        }

        $timestamp = now()->toIso8601String();
        $updated = $this->fundingRequests->update($requestId, [
            'status' => $decision,
            'status_name' => $decision,
            'approved_by' => (string) $approverId,
            'admin_notes' => $notes,
            $decision === 'approved' ? 'approved_at' : 'rejected_at' => $timestamp,
        ]);

        if ($updated === null) {
            return null;
        }

        $this->approvals->create([
            'request_id' => (string) $requestId,
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