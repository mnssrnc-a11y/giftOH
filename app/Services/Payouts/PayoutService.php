<?php

namespace App\Services\Payouts;

use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\FundQueue;
use App\Services\IotService;
use App\Services\NotificationService;
use App\Services\SystemSettings;
use App\Services\TransactionLog;
use Illuminate\Support\Facades\Cache;

/**
 * Automatic (bank-to-bank / e-wallet) transfer of approved funds to the receiver's account.
 *
 * A transfer is possible once the request is approved, its list of people is in, the donations on
 * hand cover it in the release line (first come, first served, see FundQueue), the receiver's bank
 * or e-wallet has a payout channel, and the payout account has the money. The super admin starts
 * it and confirms with an emailed code. The result comes back by webhook, or from a status check
 * (button, page load, or `php artisan payouts:sync` every minute); a successful transfer is
 * recorded as the release of funds.
 */
class PayoutService
{
    public const REFERENCE_PREFIX = 'goh-';

    private const FINAL_FAILURES = ['FAILED', 'CANCELLED', 'REVERSED', 'REJECTED', 'EXPIRED', 'COMPLIANCE_REJECTED'];

    public function __construct(
        private FundingService $funding,
        private IotService $iot,
        private NotificationService $notifications,
        private AuditLogger $audit,
        private FundQueue $queue,
        private TransactionLog $ledger,
        private SystemSettings $settings
    ) {
    }

    public function enabled(): bool
    {
        return match (config('payouts.driver')) {
            'sandbox' => true,
            'xendit' => filled(config('payouts.xendit.secret_key')),
            default => false,
        };
    }

    /** "manual", "sandbox", "test" (Xendit test key) or "live" (real money). */
    public function mode(): string
    {
        return $this->enabled() ? $this->gateway()->mode() : 'manual';
    }

    public function gateway(): PayoutGateway
    {
        return app(PayoutGateway::class);
    }

    /**
     * The payout channel of a bank / e-wallet. Names are matched ignoring case, spaces and dots
     * ("Gcash", "GCASH" and "G-Cash" are GCash), so an account saved with a slightly different
     * spelling can still be sent to. A channel saved in System settings comes before the default.
     */
    public function channelFor(?string $provider): ?string
    {
        $key = fn (?string $name): string => preg_replace('/[^a-z0-9]/', '', strtolower((string) $name));
        $wanted = $key($provider);
        if ($wanted === '') {
            return null;
        }
        if (config('payouts.driver') === 'sandbox') {
            return collect($this->gateway()->channels())->first(fn (array $channel) => $key($channel['name'] ?? '') === $wanted)['code'] ?? null;
        }
        foreach ([$this->settings->payoutChannels(), (array) config('payouts.channels', [])] as $channels) {
            foreach ($channels as $name => $code) {
                if ($key((string) $name) === $wanted) {
                    return filled($code) ? (string) $code : null;
                }
            }
        }

        return null;
    }

    /**
     * The banks and e-wallets the payout account can send to, read at most once an hour.
     *
     * @return array<int, array{code: string, name: string, category: string, minimum: ?float, maximum: ?float}>
     */
    public function channels(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember('payouts:channels:' . config('payouts.driver') . ':' . $this->mode(), 3600, fn () => $this->gateway()->channels());
    }

    /** Money in the payout account, read at most once a minute (null when it cannot be read). */
    public function balance(): ?float
    {
        if (! $this->enabled()) {
            return null;
        }
        $key = 'payouts:balance:' . config('payouts.driver');
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        $balance = $this->gateway()->balance();
        if ($balance !== null) {
            Cache::put($key, $balance, 60);
        }

        return $balance;
    }

    /**
     * @return array{ready: bool, problems: string[], amount: float, channel: ?string, provider: ?string, payout: array}
     */
    public function readiness(array $request, ?float $amount = null): array
    {
        $stage = $this->funding->stageOf($request);
        $disbursement = (array) ($request['disbursement'] ?? []);
        $payout = (array) ($disbursement['payout'] ?? []);
        $provider = $disbursement['provider'] ?? $disbursement['bank_name'] ?? null;
        $channel = $this->channelFor($provider);
        $approved = (float) ($request['approved_amount'] ?? 0);
        // The amount released (chosen on the admin's Release step), or the whole approved amount.
        $amount = round($amount ?? $approved, 2);

        $problems = [];
        if (! $this->enabled()) {
            $problems[] = 'Automatic transfers are not connected yet: the payout provider account and API key are still needed.';
        }
        if ($stage['key'] === 'awaiting_recipients') {
            $problems[] = 'The requester has not listed the people who will receive the help yet.';
        } elseif ($stage['key'] !== 'to_release') {
            $problems[] = 'Only approved requests waiting for the release of funds can be sent.';
        }
        if (in_array($payout['status'] ?? null, ['processing', 'succeeded'], true)) {
            $problems[] = 'A transfer for this request is already ' . $payout['status'] . '.';
        }
        if ($approved <= 0) {
            $problems[] = 'There is no approved amount to send.';
        } elseif ($amount <= 0) {
            $problems[] = 'Choose the amount to send.';
        } elseif ($amount > $approved + 0.005) {
            $problems[] = 'The amount cannot be more than the approved ₱' . number_format($approved, 2) . '.';
        }
        if ($channel === null) {
            $problems[] = "Automatic transfer to {$provider} is not set up. Choose its payout channel in System settings → Bank transfers, or send it from the foundation's account and record the release.";
        }
        // First come, first served: the donations on hand must cover this request after the ones before it.
        $line = $stage['key'] === 'to_release' ? $this->queue->rowFor((string) $request['id']) : null;
        if ($line !== null && ! $line['covered']) {
            $problems[] = $line['short'] > 0
                ? "Not enough donations yet: this request is #{$line['position']} in line and ₱" . number_format($line['short'], 2) . ' more is needed.'
                : 'The foundation\'s funds could not be read right now.';
        }
        // Each bank / e-wallet has its own limits (GCash: up to ₱100,000 per transfer).
        if ($problems === [] && $channel !== null) {
            try {
                $limits = collect($this->channels())->firstWhere('code', $channel);
            } catch (PayoutException $exception) {
                $limits = null; // the provider checks the limits too
            }
            if (($limits['maximum'] ?? null) !== null && $amount > $limits['maximum']) {
                $problems[] = "{$provider} takes at most ₱" . number_format($limits['maximum'], 2) . ' per transfer. Send this grant by hand and record the release.';
            } elseif (($limits['minimum'] ?? null) !== null && $amount < $limits['minimum']) {
                $problems[] = "{$provider} needs at least ₱" . number_format($limits['minimum'], 2) . ' per transfer.';
            }
        }
        // The money leaves from the payout account, so it must hold the amount.
        if ($problems === [] && ($balance = $this->balance()) !== null && $balance + 0.005 < $amount) {
            $problems[] = 'The payout account has ₱' . number_format($balance, 2) . '. Add money to it to send ₱' . number_format($amount, 2) . '.';
        }

        return ['ready' => $problems === [], 'problems' => $problems, 'amount' => $amount, 'approved' => $approved, 'channel' => $channel, 'provider' => $provider, 'payout' => $payout];
    }

    /**
     * Send the amount released (by default the whole approved amount). Call only after the admin
     * or super admin confirmed with the code emailed to them.
     *
     * @throws PayoutException
     */
    public function send(string $requestId, string|int $userId, ?float $amount = null): array
    {
        $request = $this->funding->getRequestById($requestId) ?? throw new PayoutException('The request was not found.');
        $check = $this->readiness($request, $amount);
        if (! $check['ready']) {
            throw new PayoutException($check['problems'][0]);
        }

        $disbursement = (array) $request['disbursement'];
        $attempt = (int) ($check['payout']['attempts'] ?? 0) + 1;
        $reference = self::REFERENCE_PREFIX . $requestId;
        $result = $this->gateway()->send([
            'reference_id' => $reference,
            // A new key per attempt: a retry after a failure is a new transfer, a resent request is not.
            'idempotency_key' => "{$reference}-{$attempt}",
            'channel_code' => $check['channel'],
            'account_number' => (string) ($disbursement['account_number'] ?? ''),
            'account_name' => (string) ($disbursement['account_name'] ?? ''),
            'amount' => $check['amount'],
            'description' => 'Gift of Hope grant ' . substr($requestId, 0, 8),
        ]);

        $payout = [
            'status' => 'processing',
            'provider_status' => $result['status'],
            'payout_id' => $result['id'],
            'reference_id' => $reference,
            'amount' => $check['amount'],
            'channel_code' => $check['channel'],
            'mode' => $this->mode(),
            'requested_by' => (string) $userId,
            'requested_at' => now()->toIso8601String(),
            'attempts' => $attempt,
        ];
        $this->funding->setPayout($requestId, $payout);
        Cache::forget('payouts:balance:' . config('payouts.driver'));
        $this->audit->record('funding', 'Automatic transfer sent: ₱' . number_format($check['amount'], 2) . " to {$check['provider']}", $requestId, ['payout_id' => $result['id'], 'mode' => $payout['mode']]);
        $this->ledger->record('payout_sent', $request, $check['amount'], ['to' => $check['provider'], 'payout_id' => $result['id'], 'mode' => $this->modeLabel($payout['mode'])]);

        return $payout;
    }

    /**
     * Ask the provider how a transfer in progress ended (when its webhook has not arrived).
     * Returns the payout status now stored ("processing", "succeeded", "failed"), or null.
     */
    public function sync(string $requestId): ?string
    {
        $request = $this->funding->getRequestById($requestId);
        $payout = (array) ($request['disbursement']['payout'] ?? []);
        if ($request === null || ($payout['status'] ?? null) !== 'processing' || blank($payout['payout_id'] ?? null) || ! $this->enabled()) {
            return $payout['status'] ?? null;
        }

        $result = $this->gateway()->status((string) $payout['payout_id']);
        $this->applyResult($request, $result['status'], $result['id'], $result['failure_code'] ?? null);

        return $this->funding->getRequestById($requestId)['disbursement']['payout']['status'] ?? null;
    }

    /** sync() every transfer still in progress (php artisan payouts:sync). @return int how many were checked */
    public function syncAll(): int
    {
        $checked = 0;
        foreach ($this->funding->getApprovedRequests() as $request) {
            if (($request['disbursement']['payout']['status'] ?? null) === 'processing') {
                try {
                    $this->sync((string) $request['id']);
                    $checked++;
                } catch (PayoutException $exception) {
                    report($exception);
                }
            }
        }

        return $checked;
    }

    /**
     * Result of a transfer reported by the provider. Returns false when it is not ours.
     */
    public function handleWebhook(array $payload): bool
    {
        $data = (array) ($payload['data'] ?? $payload);
        $reference = (string) ($data['reference_id'] ?? '');
        if (! str_starts_with($reference, self::REFERENCE_PREFIX)) {
            return false;
        }
        $request = $this->funding->getRequestById(substr($reference, strlen(self::REFERENCE_PREFIX)));
        if ($request === null) {
            return false;
        }

        $payout = (array) ($request['disbursement']['payout'] ?? []);
        $this->applyResult($request, strtoupper((string) ($data['status'] ?? '')), (string) ($data['id'] ?? $data['payout_id'] ?? $payout['payout_id'] ?? ''), $data['failure_code'] ?? null);

        return true;
    }

    /** Record what the provider says: success becomes the release of funds; a failure is reported. */
    private function applyResult(array $request, string $status, string $payoutId, ?string $failureCode): void
    {
        $requestId = (string) $request['id'];
        $payout = (array) ($request['disbursement']['payout'] ?? []);
        $organization = $request['org_name'] ?? 'the requester';
        $amount = (float) ($payout['amount'] ?? $request['approved_amount'] ?? 0);
        $mode = $payout['mode'] ?? $this->mode();

        if ($status === 'SUCCEEDED') {
            if (($payout['status'] ?? null) === 'succeeded') {
                return; // already recorded (providers may resend webhooks)
            }
            $this->funding->setPayout($requestId, array_merge($payout, ['status' => 'succeeded', 'provider_status' => $status, 'completed_at' => now()->toIso8601String()]));
            $this->funding->recordDisbursement($requestId, [
                'method' => $request['disbursement']['method'] ?? 'bank_transfer',
                'amount' => $amount,
                'released_at' => now()->toDateString(),
                'reference_no' => $payoutId,
                'notes' => $mode === 'sandbox' ? 'Practice transfer through the sandbox: no real money moved.' : 'Sent automatically through the payout provider.',
            ], 'system');
            $message = '₱' . number_format($amount, 2) . " reached {$organization}'s account. The requester was asked for the liquidation.";
            $this->notifications->notifySuperAdmins($requestId, 'payout_succeeded', 'Transfer completed', $message);
            $this->notifications->notifyAdmins($requestId, 'payout_succeeded', 'Transfer completed', $message);
            $this->audit->record('funding', 'Automatic transfer completed: ₱' . number_format($amount, 2), $requestId, ['payout_id' => $payoutId]);
            $this->ledger->record('transferred', $request, $amount, [
                'method' => 'automatic', 'mode' => $this->modeLabel($mode), 'to' => $request['disbursement']['provider'] ?? null, 'reference_no' => $payoutId,
                'funds_on_hand_after' => round((float) $this->iot->getBalance(), 2),
            ], 'Payout provider');
            Cache::forget('payouts:balance:' . config('payouts.driver'));
            // The money left: the next request in line may now be covered.
            $this->queue->refresh();
        } elseif (in_array($status, self::FINAL_FAILURES, true)) {
            if (($payout['status'] ?? null) === 'failed') {
                return;
            }
            $failure = (string) ($failureCode ?: $status);
            $this->funding->setPayout($requestId, array_merge($payout, ['status' => 'failed', 'provider_status' => $status, 'failure_code' => $failure, 'completed_at' => now()->toIso8601String()]));
            $failed = "The transfer to {$organization} ended as {$status} ({$failure})." . ($status === 'REVERSED' ? ' It was returned after it had succeeded: check the account and the release record.' : ' Check the account details, then try again or send it manually.');
            $this->notifications->notifySuperAdmins($requestId, 'payout_failed', 'Transfer did not go through', $failed);
            $this->notifications->notifyAdmins($requestId, 'payout_failed', 'Transfer did not go through', $failed);
            $this->audit->record('funding', "Automatic transfer {$status}: {$failure}", $requestId, ['payout_id' => $payoutId]);
            $this->ledger->record('payout_failed', $request, $amount, ['status' => $status, 'failure_code' => $failure, 'payout_id' => $payoutId, 'mode' => $this->modeLabel($mode)], 'Payout provider');
        } elseif ($status !== '' && $status !== ($payout['provider_status'] ?? null)) {
            $this->funding->setPayout($requestId, array_merge($payout, ['provider_status' => $status]));
        }
    }

    public function modeLabel(string $mode): string
    {
        return match ($mode) {
            'live' => 'Xendit (real money)',
            'test' => 'Xendit test mode (no real money)',
            'sandbox' => 'Sandbox (no real money)',
            default => 'Manual',
        };
    }
}
