<?php

namespace App\Repositories;

class FirebaseLogRepository extends FirebaseRepository
{
    /** Not cached between requests: devices and services append to it directly. */
    protected int $cacheSeconds = 0;

    public function findByUserId(string|int $userId): array
    {
        return $this->queryBy('user_id', $userId);
    }

    protected function nodeKey(): string
    {
        return 'activity_logs';
    }

    public function forNode(string $node): static
    {
        $this->node = $node;

        return $this;
    }
}
