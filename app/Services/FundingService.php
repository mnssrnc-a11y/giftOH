<?php

namespace App\Services;

use App\Repositories\FirebaseFundingRepository;

class FundingService
{
    public function __construct(
        private FirebaseFundingRepository $fundingRequests
    ) {}

    public function createRequest(array $data): array
    {
        return $this->fundingRequests->create($data);
    }

    public function getRequestById(string|int $id): ?array
    {
        return $this->fundingRequests->findById($id);
    }

    public function getRequestsByUser(string|int $userId): array
    {
        return $this->fundingRequests->findByUserId($userId);
    }
}