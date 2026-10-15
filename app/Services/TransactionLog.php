<?php

namespace App\Services;

use App\Repositories\FirebaseTransactionLogRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * The ledger of every money step on a request: amount approved, funds set aside, money
 * transferred (by hand or automatically), failed transfers, cancellations and verified
 * liquidations.
 *
 * Entries cannot be edited or deleted: there is no way to do it in the app, and each entry carries
 * the hash of the one before it, so changing or removing an entry in the database breaks the chain
 * and verify() points at it.
 */
class TransactionLog
{
    public const TYPES = [
        'approved' => 'Amount approved',
        'set_aside' => 'Funds set aside · ready to transfer',
        'transferred' => 'Money transferred',
        'payout_sent' => 'Automatic transfer sent',
        'payout_failed' => 'Automatic transfer failed',
        'cancelled' => 'Request cancelled',
        'liquidation_verified' => 'Liquidation verified',
    ];

    private const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private FirebaseTransactionLogRepository $entries)
    {
    }

    /**
     * Add an entry. Never throws: a ledger hiccup must not undo the money step it records (it is
     * reported, and verify() still checks the chain).
     *
     * @param  array  $request  the funding request (id, org_name)
     */
    public function record(string $type, array $request, ?float $amount, array $details = [], Authenticatable|string|null $actor = null): ?array
    {
        $actor ??= Auth::user();

        try {
            return Cache::lock('transaction-log:append', 10)->block(5, function () use ($type, $request, $amount, $details, $actor) {
                $last = collect($this->entries->inOrder())->last();
                $entry = [
                    'seq' => (int) ($last['seq'] ?? 0) + 1,
                    'type' => $type,
                    'label' => self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                    'request_id' => (string) ($request['id'] ?? ''),
                    'organization' => $request['org_name'] ?? null,
                    'amount' => $amount !== null ? round($amount, 2) : null,
                    'details' => $details,
                    'actor_id' => $actor instanceof Authenticatable ? (string) $actor->getAuthIdentifier() : 'system',
                    'actor_name' => $actor instanceof Authenticatable
                        ? (method_exists($actor, 'fullName') ? ($actor->fullName() ?: 'Staff') : 'Staff')
                        : ($actor ?: 'System'),
                    'at' => now()->toIso8601String(),
                    'prev_hash' => $last['hash'] ?? self::GENESIS,
                ];
                $entry['hash'] = self::hash($entry);
                $entry['id'] = sprintf('tx-%010d', $entry['seq']);

                return $this->entries->create($entry);
            });
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** Newest first; for one request when $requestId is given. */
    public function latest(?string $requestId = null, int $limit = 500): array
    {
        $entries = array_reverse($this->entries->inOrder());
        if ($requestId !== null) {
            $entries = array_values(array_filter($entries, fn (array $entry): bool => (string) ($entry['request_id'] ?? '') === $requestId));
        }

        return array_slice($entries, 0, $limit);
    }

    /**
     * Check every entry against its hash and the one before it.
     *
     * @return array{ok: bool, count: int, broken: ?int}  broken = seq of the first changed or missing entry
     */
    public function verify(): array
    {
        $previous = self::GENESIS;
        $expected = 1;
        foreach ($this->entries->inOrder() as $entry) {
            $seq = (int) ($entry['seq'] ?? 0);
            if ($seq !== $expected || ($entry['prev_hash'] ?? null) !== $previous || ($entry['hash'] ?? null) !== self::hash($entry)) {
                return ['ok' => false, 'count' => $expected - 1, 'broken' => $expected];
            }
            $previous = $entry['hash'];
            $expected++;
        }

        return ['ok' => true, 'count' => $expected - 1, 'broken' => null];
    }

    /**
     * Hash of an entry's content. Values are normalised the way Firebase stores them (numbers as
     * numbers, no empty values), so an entry read back hashes the same as when it was written.
     */
    public static function hash(array $entry): string
    {
        unset($entry['hash'], $entry['id'], $entry['created_at'], $entry['updated_at']);

        return hash('sha256', json_encode(self::normalise($entry), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = array_filter(array_map([self::class, 'normalise'], $value), static fn ($item) => $item !== null && $item !== []);
            ksort($value);

            return $value;
        }

        return is_int($value) || is_float($value) ? sprintf('%.6F', $value) : $value;
    }
}
