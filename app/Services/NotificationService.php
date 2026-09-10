<?php

namespace App\Services;

use App\Repositories\FirebaseNotificationRepository;
use App\Repositories\FirebaseNotificationHistoryRepository;

class NotificationService
{
    public function __construct(
        private FirebaseNotificationRepository $notifications,
        private FirebaseNotificationHistoryRepository $history
    ) {
    }

    public function createFundingDecision(string|int $userId, string|int $requestId, string $decision, string $organization): array
    {
        $isApproved = $decision === 'approved';

        return $this->notifications->create([
            'user_id' => $userId,
            'request_id' => (string) $requestId,
            'type' => $isApproved ? 'funding_approved' : 'funding_denied',
            'label' => $isApproved ? 'Fund Request Approved' : 'Fund Request Denied',
            'message' => "Your fund request for {$organization} was " . ($isApproved ? 'approved.' : 'denied.'),
            'read' => false,
        ]);
    }

    public function getByUser(string|int $userId): array
    {
        $notifications = $this->notifications->findByUserId($userId);

        usort($notifications, static function (array $first, array $second): int {
            return strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? ''));
        });

        return $notifications;
    }

    public function markAsRead(string|int $notificationId, string|int $userId): bool
    {
        $notification = $this->notifications->findById($notificationId);

        if (! $notification || (string) ($notification['user_id'] ?? '') !== (string) $userId) {
            return false;
        }

        $notification['read'] = true;
        $notification['read_at'] = now()->toIso8601String();
        $this->history->create($notification);

        return $this->notifications->delete($notificationId);
    }
}