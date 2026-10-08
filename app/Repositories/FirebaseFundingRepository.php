<?php

namespace App\Repositories;

use Kreait\Firebase\Exception\Database\UnsupportedQuery;

class FirebaseFundingRepository extends FirebaseRepository
{
    public function findByUserId(string|int $userId): array
    {
        try {
            $requests = $this->queryBy('user_id', $userId);
            if ($requests !== []) {
                return $requests;
            }
        } catch (UnsupportedQuery) {
        }

        return array_values(array_filter(
            $this->all(),
            static fn (array $request): bool => (string) ($request['user_id'] ?? '') === (string) $userId
        ));
    }

    public function findByStatus(string|int $statusId): array
    {
        return $this->queryBy('status_id', $statusId);
    }

    /**
     * Append an entry under a list on the request (e.g. staff_notes) without rewriting the request,
     * so two admins adding notes at once cannot overwrite each other. Returns the new key.
     */
    public function pushChild(string|int $id, string $child, array $data): string
    {
        $key = $this->reference((string) $id)->getChild($child)->push($data)->getKey();
        $this->nodeChanged();

        return $key;
    }

    protected function nodeKey(): string
    {
        return 'funding_requests';
    }
}
