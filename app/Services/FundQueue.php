<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * The release line: approved requests waiting for their transfer, first approved first (first
 * come, first served). The donations on hand (IoT boxes + recorded donations, minus what was
 * already transferred) are focused on the first request in line; when they cover it, the admins
 * are told to transfer the money to the requester's bank or e-wallet, and the next request in
 * line is funded after it. Nobody skips the line, even a smaller request that would fit.
 */
class FundQueue
{
    public function __construct(
        private FundingService $funding,
        private IotService $iot,
        private NotificationService $notifications,
        private TransactionLog $ledger
    ) {
    }

    /**
     * @return array{balance: ?float, set_aside: float, owed: float, free: ?float, rows: array<int, array>}
     *   balance = funds on hand; set_aside = what the covered requests take; owed = every approved
     *   amount still to transfer; free = what is left to grant.
     */
    public function state(): array
    {
        $balance = $this->iot->getBalance();
        $line = $this->funding->awaitingTransfer();
        $rows = [];
        $cumulative = 0.0;
        $blocked = $balance === null;

        foreach ($line as $index => $request) {
            $amount = $this->funding->grantedAmountOf($request);
            $cumulative += $amount;
            $covered = ! $blocked && $cumulative <= $balance + 0.005;
            $blocked = $blocked || ! $covered;
            $hasList = $this->funding->hasBeneficiaryList($request);
            // Donations already set aside for this request (what is left after the ones before it).
            $raised = $balance === null ? null : ($covered ? $amount : round(max(0.0, min($amount, $balance - ($cumulative - $amount))), 2));
            $short = $covered || $balance === null ? 0.0 : round($cumulative - $balance, 2);
            $rows[] = [
                'id' => (string) $request['id'],
                'organization' => $request['org_name'] ?? 'Request',
                'amount' => $amount,
                'approved_at' => $request['approved_at'] ?? null,
                'position' => $index + 1,
                'covered' => $covered,
                'has_list' => $hasList,
                'ready' => $covered && $hasList,
                // Donations still needed before this request is covered (everyone before it first).
                'short' => $short,
                'raised' => $raised,
                // The part of "short" that the requests approved earlier still need.
                'ahead_short' => $raised === null ? 0.0 : round(max(0.0, $short - ($amount - $raised)), 2),
                // False when the donation total could not be read (short is unknown, not zero).
                'known' => $balance !== null,
                'notified' => ! empty($request['funds_ready_at']),
                'request' => $request,
            ];
        }

        $owed = $cumulative;
        $setAside = array_sum(array_map(fn (array $row): float => $row['covered'] ? $row['amount'] : 0.0, $rows));

        return [
            'balance' => $balance,
            'set_aside' => $setAside,
            'owed' => $owed,
            'free' => $balance === null ? null : max(0.0, $balance - $owed),
            'rows' => $rows,
        ];
    }

    /** This request's place in the line, or null when it is not waiting for a transfer. */
    public function rowFor(string $requestId, ?array $state = null): ?array
    {
        foreach (($state ?? $this->state())['rows'] as $row) {
            if ($row['id'] === $requestId) {
                return $row;
            }
        }

        return null;
    }

    /** The donations on hand cover this request and its list of people is in: transfer now. */
    public function isReady(string $requestId): bool
    {
        return (bool) ($this->rowFor($requestId)['ready'] ?? false);
    }

    /**
     * Tell the admins about every request that has just become ready to transfer (once each), and
     * log the funds set aside for it. Run after approvals, transfers, cancellations and lists of
     * people, and every few minutes for new donations (php artisan funds:queue).
     *
     * @return string[] ids of the requests announced now
     */
    public function refresh(): array
    {
        // One at a time, so a request is never announced twice.
        $lock = Cache::lock('fund-queue:refresh', 30);
        if (! $lock->get()) {
            return [];
        }

        try {
            return $this->announceReady();
        } finally {
            $lock->release();
        }
    }

    private function announceReady(): array
    {
        $state = $this->state();
        $announced = [];
        foreach ($state['rows'] as $row) {
            if (! $row['ready'] || $row['notified']) {
                continue;
            }
            $this->funding->markFundsReady($row['id']);
            $disbursement = (array) ($row['request']['disbursement'] ?? []);
            $to = trim(($disbursement['provider'] ?? $disbursement['bank_name'] ?? 'the account') . ' · ' . ($disbursement['account_name'] ?? ''), ' ·');
            $this->notifications->notifyAdmins($row['id'], 'funds_ready', 'Funds ready · transfer now',
                '₱' . number_format($row['amount'], 2) . " for {$row['organization']} is covered by the donations on hand (#{$row['position']} in line). "
                . "Transfer it to {$to}, then record the transfer.");
            $this->ledger->record('set_aside', $row['request'], $row['amount'], [
                'position' => $row['position'],
                'funds_on_hand' => round((float) $state['balance'], 2),
            ], 'System');
            $announced[] = $row['id'];
        }

        return $announced;
    }

    /** refresh() at most once a minute, for page loads (new donations arrive from the boxes). */
    public function refreshSoon(): void
    {
        if (Cache::add('fund-queue:refreshed', true, 60)) {
            try {
                $this->refresh();
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
