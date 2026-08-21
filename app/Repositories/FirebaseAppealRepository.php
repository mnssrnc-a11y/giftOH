<?php

namespace App\Repositories;

class FirebaseAppealRepository extends FirebaseRepository
{
    public function findByOriginalRequestId(string|int $requestId): array
    {
        return $this->queryBy('original_request_id', $requestId);
    }

    protected function nodeKey(): string
    {
        return 'funding_appeals';
    }
}
