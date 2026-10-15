<?php

namespace App\Repositories;

use LogicException;

/**
 * Firebase transaction_logs: the ledger of money movements. Entries are only ever added; this
 * repository refuses to change or remove one (see App\Services\TransactionLog).
 */
class FirebaseTransactionLogRepository extends FirebaseRepository
{
    public function update(string|int $id, array $data): ?array
    {
        throw new LogicException('Transaction logs cannot be edited.');
    }

    public function delete(string|int $id): bool
    {
        throw new LogicException('Transaction logs cannot be deleted.');
    }

    /** Oldest first. */
    public function inOrder(): array
    {
        $entries = $this->all();
        usort($entries, static fn (array $a, array $b): int => (int) ($a['seq'] ?? 0) <=> (int) ($b['seq'] ?? 0));

        return $entries;
    }

    protected function nodeKey(): string
    {
        return 'transaction_logs';
    }
}
