<?php

namespace App\Services;

use App\Repositories\FirebaseFundingRepository;

class FundingService
{
    public function __construct(
        private FirebaseFundingRepository $fundingRequests
    ) {}

    public function createRequest(array $data): array
    {
        return $this->fundingRequests->create($data);
    }

    public function getRequestById(string|int $id): ?array
    {
        return $this->fundingRequests->findById($id);
    }

    public function getRequestsByUser(string|int $userId): array
    {
        return $this->fundingRequests->findByUserId($userId);
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