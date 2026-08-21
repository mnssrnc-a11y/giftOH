<?php

namespace App\Repositories;

class FirebaseDonationRepository extends FirebaseRepository
{
    public function findByUserId(string|int $userId): array
    {
        return $this->queryBy('user_id', $userId);
    }

    public function findByIotBoxId(string|int $iotBoxId): array
    {
        return $this->queryBy('iot_box_id', $iotBoxId);
    }

    protected function nodeKey(): string
    {
        return 'donations';
    }
}
