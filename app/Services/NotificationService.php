<?php

namespace App\Services;

use App\Http\Middleware\EnsureRole;
use App\Repositories\FirebaseNotificationHistoryRepository;
use App\Repositories\FirebaseNotificationRepository;
use App\Repositories\FirebaseUserRepository;
use Illuminate\Support\Facades\Log;

/**
 * In-app notifications. Requesters hear about their own requests; admins and super admins hear
 * about the work waiting for them (new requests, replies, decisions to finalize, price updates).
 * Opening a notification moves it to notification_history and follows its link.
 */
class NotificationService
{
    public function __construct(
        private FirebaseNotificationRepository $notifications,
        private FirebaseNotificationHistoryRepository $history,
        private FirebaseUserRepository $users
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
            'link' => route('fund-request.show', $requestId, false),
            'read' => false,
        ]);
    }

    /**
     * Progress update on a funding request (interview, release, liquidation...).
     */
    public function notify(string|int $userId, string|int $requestId, string $type, string $label, string $message): ?array
    {
        if ((string) $userId === '') {
            return null;
        }

        return $this->notifications->create([
            'user_id' => $userId,
            'request_id' => (string) $requestId,
            'type' => $type,
            'label' => $label,
            'message' => $message,
            'link' => route('fund-request.show', $requestId, false),
            'read' => false,
        ]);
    }

    /**
     * Notify every active account with this role ("admin" or "super_admin"). A failure is logged,
     * never shown: the action that triggered it has already succeeded.
     *
     * @return int how many accounts were notified
     */
    public function notifyRole(string $role, string $type, string $label, string $message, ?string $link = null, string|int|null $requestId = null, string|int|null $except = null): int
    {
        try {
            $sent = 0;
            foreach ($this->users->all() as $user) {
                if (EnsureRole::resolveRole((object) $user) !== $role
                    || ! filter_var($user['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)
                    || ($except !== null && (string) $user['id'] === (string) $except)) {
                    continue;
                }
                $this->notifications->create([
                    'user_id' => (string) $user['id'],
                    'request_id' => $requestId === null ? null : (string) $requestId,
                    'type' => $type,
                    'label' => $label,
                    'message' => $message,
                    'link' => $link,
                    'audience' => $role,
                    'read' => false,
                ]);
                $sent++;
            }

            return $sent;
        } catch (\Throwable $exception) {
            Log::warning('Staff notification could not be saved', ['role' => $role, 'type' => $type, 'error' => $exception->getMessage()]);

            return 0;
        }
    }

    /** Notify admins about a funding request; the link opens the admin's request page. */
    public function notifyAdmins(string|int $requestId, string $type, string $label, string $message, string|int|null $except = null): int
    {
        return $this->notifyRole('admin', $type, $label, $message, route('admin.fund-request.show', $requestId, false), $requestId, $except);
    }

    /** Notify super admins about a funding request; the link opens the request for final checking. */
    public function notifySuperAdmins(string|int $requestId, string $type, string $label, string $message): int
    {
        return $this->notifyRole('super_admin', $type, $label, $message, route('superadmin.fund-request.show', $requestId, false), $requestId);
    }

    public function getByUser(string|int $userId): array
    {
        try {
            $notifications = $this->notifications->findByUserId($userId);
        } catch (\Throwable $exception) {
            Log::warning('Notifications could not be read', ['error' => $exception->getMessage()]);

            return [];
        }

        usort($notifications, static function (array $first, array $second): int {
            return strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? ''));
        });

        return $notifications;
    }

    /**
     * Mark one notification as read (it moves to the history).
     *
     * @return array|null the notification, or null when it is not this user's
     */
    public function markAsRead(string|int $notificationId, string|int $userId): ?array
    {
        $notification = $this->notifications->findById($notificationId);

        if (! $notification || (string) ($notification['user_id'] ?? '') !== (string) $userId) {
            return null;
        }

        $notification['read'] = true;
        $notification['read_at'] = now()->toIso8601String();
        $this->history->create($notification);
        $this->notifications->delete($notificationId);

        return $notification;
    }

    public function markAllAsRead(string|int $userId): int
    {
        $count = 0;
        foreach ($this->getByUser($userId) as $notification) {
            if ($this->markAsRead($notification['id'], $userId)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Where opening a notification should take this person: its own link when it is a path on
     * this site, otherwise the notifications page.
     */
    public static function safeLink(?string $link): ?string
    {
        return is_string($link) && str_starts_with($link, '/') && ! str_starts_with($link, '//') ? $link : null;
    }
}
