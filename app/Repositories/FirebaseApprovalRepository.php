<?php

namespace App\Repositories;

class FirebaseApprovalRepository extends FirebaseRepository
{
    public function findByRequestId(string|int $requestId): array
    {
        return $this->queryBy('request_id', $requestId);
    }

    public function findByApproverId(string|int $userId): array
    {
        return $this->queryBy('approved_by', $userId);
    }

    protected function nodeKey(): string
    {
        return 'funding_approvals';
    }
}
