<?php

namespace App\Repositories;

use Kreait\Firebase\Exception\Database\UnsupportedQuery;

class FirebaseNotificationRepository extends FirebaseRepository
{
    public function findByUserId(string|int $userId): array
    {
        try {
            return $this->queryBy('user_id', $userId);
        } catch (UnsupportedQuery) {
            return array_values(array_filter(
                $this->all(),
                static fn (array $notification): bool => (string) ($notification['user_id'] ?? '') === (string) $userId
            ));
        }
    }

    protected function nodeKey(): string
    {
        return 'notifications';
    }
}