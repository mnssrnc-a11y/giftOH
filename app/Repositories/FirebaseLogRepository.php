<?php

namespace App\Repositories;

class FirebaseLogRepository extends FirebaseRepository
{
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
