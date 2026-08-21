<?php

namespace App\Repositories;

class FirebaseFundingRepository extends FirebaseRepository
{
    public function findByUserId(string|int $userId): array
    {
        return $this->queryBy('user_id', $userId);
    }

    public function findByStatus(string|int $statusId): array
    {
        return $this->queryBy('status_id', $statusId);
    }

    protected function nodeKey(): string
    {
        return 'funding_requests';
    }
}
