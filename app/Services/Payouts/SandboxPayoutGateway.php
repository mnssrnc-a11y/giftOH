<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A practice bank for demos and testing (PAYOUT_DRIVER=sandbox): it behaves like Xendit's test
 * mode, but NO real money moves. A transfer is accepted at once and confirmed a few seconds later
 * (config payouts.sandbox.settle_seconds); Xendit's failing test account numbers fail the same
 * way; the practice payout account starts with payouts.sandbox.balance and goes down with each
 * confirmed transfer.
 */
class SandboxPayoutGateway implements PayoutGateway
{
    private const PREFIX = 'payouts:sandbox:';

    public function send(array $transfer): array
    {
        $amount = round((float) $transfer['amount'], 2);
        if ($amount > ($this->balance() ?? 0) + 0.005) {
            throw new PayoutException('The practice payout account does not have enough money (INSUFFICIENT_BALANCE).');
        }

        // Same idempotency key, same transfer: never paid twice.
        $existing = Cache::get(self::PREFIX . 'key:' . $transfer['idempotency_key']);
        if ($existing && ($payout = Cache::get(self::PREFIX . $existing))) {
            return ['id' => $payout['id'], 'status' => $this->resolve($payout)['status']];
        }

        $payout = [
            'id' => 'sbx_' . Str::lower(Str::random(24)),
            'reference_id' => $transfer['reference_id'],
            'channel_code' => $transfer['channel_code'],
            'account_number' => preg_replace('/\D/', '', (string) $transfer['account_number']),
            'amount' => $amount,
            'created_at' => now()->getTimestamp(),
            'counted' => false,
        ];
        Cache::forever(self::PREFIX . $payout['id'], $payout);
        Cache::forever(self::PREFIX . 'key:' . $transfer['idempotency_key'], $payout['id']);

        return ['id' => $payout['id'], 'status' => 'ACCEPTED'];
    }

    public function status(string $payoutId): array
    {
        $payout = Cache::get(self::PREFIX . $payoutId) ?? throw new PayoutException("The practice bank has no transfer {$payoutId}.");

        return ['id' => $payoutId] + $this->resolve($payout);
    }

    public function balance(): ?float
    {
        return round((float) config('payouts.sandbox.balance', 0) - (float) Cache::get(self::PREFIX . 'sent', 0), 2);
    }

    public function channels(): array
    {
        $channels = [];
        foreach ((array) config('funding.transfer_modes', []) as $mode => $definition) {
            foreach ((array) ($definition['providers'] ?? []) as $provider) {
                $channels[] = ['code' => 'SBX_' . Str::upper(Str::slug($provider, '_')), 'name' => $provider, 'category' => $mode === 'e_wallet' ? 'EWALLET' : 'BANK', 'minimum' => 1.0, 'maximum' => null];
            }
        }

        return $channels;
    }

    public function mode(): string
    {
        return 'sandbox';
    }

    /** Accepted until it settles; then succeeded (and taken from the practice balance once) or failed. */
    private function resolve(array $payout): array
    {
        if (now()->getTimestamp() - (int) $payout['created_at'] < (int) config('payouts.sandbox.settle_seconds', 20)) {
            return ['status' => 'ACCEPTED', 'failure_code' => null];
        }

        $failure = ((array) config('payouts.sandbox.failing_accounts', []))[$payout['account_number']] ?? null;
        if ($failure !== null) {
            return ['status' => 'FAILED', 'failure_code' => $failure];
        }

        if (! $payout['counted']) {
            Cache::forever(self::PREFIX . 'sent', (float) Cache::get(self::PREFIX . 'sent', 0) + (float) $payout['amount']);
            Cache::forever(self::PREFIX . $payout['id'], ['counted' => true] + $payout);
        }

        return ['status' => 'SUCCEEDED', 'failure_code' => null];
    }
}
