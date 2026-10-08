<?php

namespace App\Repositories;

use Kreait\Firebase\Exception\Database\UnsupportedQuery;

class FirebaseMessageRepository extends FirebaseRepository
{
    /**
     * Messages of one request, oldest first. Works with or without the request_id index.
     */
    public function forRequest(string $requestId): array
    {
        try {
            $messages = $this->queryBy('request_id', $requestId);
        } catch (UnsupportedQuery|\Throwable) {
            $messages = array_values(array_filter($this->all(), static fn (array $m): bool => ($m['request_id'] ?? '') === $requestId));
        }
        usort($messages, static fn (array $a, array $b): int => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));

        return $messages;
    }

    protected function nodeKey(): string
    {
        return 'request_messages';
    }
}
