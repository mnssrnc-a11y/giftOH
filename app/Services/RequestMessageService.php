<?php

namespace App\Services;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseMessageRepository;

/**
 * Conversation between a requester and the foundation staff on one funding request
 * (interview scheduling, assessment questions, document resubmission). System messages
 * record events such as a scheduled interview or a document review.
 */
class RequestMessageService
{
    public function __construct(
        private FirebaseMessageRepository $messages,
        private NotificationService $notifications
    ) {
    }

    /**
     * @return array<int, array{id: string, side: string, sender: string, body: string, at: ?string, unread: bool}>
     */
    public function thread(string $requestId, string $viewerSide): array
    {
        return array_map(fn (array $message): array => [
            'id' => (string) $message['id'],
            'side' => $message['side'] ?? 'system',
            'sender' => $message['sender_name'] ?? 'Gift of Hope',
            'body' => (string) ($message['body'] ?? ''),
            'at' => $message['created_at'] ?? null,
            'unread' => $viewerSide === 'staff' ? ! ($message['read_by_staff'] ?? false) : ! ($message['read_by_requester'] ?? false),
        ], $this->messages->forRequest($requestId));
    }

    public function send(array $request, FirebaseUser $sender, string $body): array
    {
        $isStaff = $sender->isAdmin() || $sender->isSuperAdmin();
        $message = $this->messages->create([
            'request_id' => (string) $request['id'],
            'sender_id' => (string) $sender->getAuthIdentifier(),
            'sender_name' => $isStaff ? (($sender->fullName() ?: 'Admin') . ' · Gift of Hope') : ($sender->fullName() ?: 'Requester'),
            'side' => $isStaff ? 'staff' : 'requester',
            'body' => $body,
            'read_by_staff' => $isStaff,
            'read_by_requester' => ! $isStaff,
        ]);

        if ($isStaff) {
            $this->notifications->notify($request['user_id'] ?? '', $request['id'], 'message', 'New message from Gift of Hope',
                'About your request for ' . ($request['org_name'] ?? 'your organization') . ': ' . mb_strimwidth($body, 0, 120, '…'));
        } else {
            $this->notifications->notifyAdmins($request['id'], 'message', 'New message from ' . ($request['org_name'] ?? 'a requester'),
                ($sender->fullName() ?: 'The requester') . ': ' . mb_strimwidth($body, 0, 120, '…'));
        }

        return $message;
    }

    /**
     * An event written into the conversation (visible to both sides).
     */
    public function system(string $requestId, string $body, ?string $unreadFor = null): void
    {
        $this->messages->create([
            'request_id' => $requestId,
            'sender_id' => 'system',
            'sender_name' => 'Gift of Hope',
            'side' => 'system',
            'body' => $body,
            'read_by_staff' => $unreadFor !== 'staff',
            'read_by_requester' => $unreadFor !== 'requester',
        ]);
    }

    public function markRead(string $requestId, string $side): void
    {
        $field = $side === 'staff' ? 'read_by_staff' : 'read_by_requester';
        foreach ($this->messages->forRequest($requestId) as $message) {
            if (! ($message[$field] ?? false)) {
                $this->messages->update($message['id'], [$field => true]);
            }
        }
    }

    /**
     * Unread requester messages per request, for the admin list and notifications.
     *
     * @return array<string, array{count: int, last_at: string}>
     */
    public function unreadForStaff(): array
    {
        $unread = [];
        try {
            foreach ($this->messages->all() as $message) {
                if (($message['side'] ?? '') === 'requester' && ! ($message['read_by_staff'] ?? false)) {
                    $id = (string) $message['request_id'];
                    $unread[$id]['count'] = ($unread[$id]['count'] ?? 0) + 1;
                    $unread[$id]['last_at'] = max($unread[$id]['last_at'] ?? '', (string) ($message['created_at'] ?? ''));
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $unread;
    }
}
