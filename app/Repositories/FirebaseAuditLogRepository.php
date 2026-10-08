<?php

namespace App\Repositories;

class FirebaseAuditLogRepository extends FirebaseRepository
{
    /**
     * Newest first.
     */
    public function latest(int $limit = 200): array
    {
        $logs = $this->all();
        usort($logs, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($logs, 0, $limit);
    }

    protected function nodeKey(): string
    {
        return 'audit_logs';
    }
}
