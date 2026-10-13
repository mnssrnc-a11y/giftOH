<?php

namespace App\Services\Payouts;

use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\IotService;
use App\Services\NotificationService;

/**
 * Automatic transfer of approved funds to the receiver's bank account or e-wallet.
 *
 * A transfer is possible once the request is approved, its list of people is in, the foundation's
 * funds cover every approved amount, and the receiver's bank or e-wallet has a payout channel. The
 * super admin starts it and confirms with an emailed code; the provider reports the result by
 * webhook, and a successful transfer is recorded as the release of funds.
 */
class PayoutService
{
    public const REFERENCE_PREFIX = 'goh-';

    public function __construct(
        private FundingService $funding,
        private IotService $iot,
        private NotificationService $notifications,
        private AuditLogger $audit
    ) {
    }

    public function enabled(): bool
    {
        return config('payouts.driver') === 'xendit' && filled(config('payouts.xendit.secret_key'));
    }

    public function channelFor(?string $provider): ?string
    {
        $code = ((array) config('payouts.channels', []))[$provider] ?? null;

        return filled($code) ? (string) $code : null;
    }

    /**
     * @return array{ready: bool, problems: string[], amount: float, channel: ?string, provider: ?string, payout: array}
     */
    public function readiness(array $request): array
    {
        $stage = $this->funding->stageOf($request);
        $disbursement = (array) ($request['disbursement'] ?? []);
        $payout = (array) ($disbursement['payout'] ?? []);
        $provider = $disbursement['provider'] ?? $disbursement['bank_name'] ?? null;
        $channel = $this->channelFor($provider);
        $amount = (float) ($request['approved_amount'] ?? 0);

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
        if ($amount <= 0) {
            $problems[] = 'There is no approved amount to send.';
        }
        if ($channel === null) {
            $problems[] = "Automatic transfer to {$provider} is not set up. Send it from the foundation's account and record the release.";
        }
        $total = $this->iot->getTotalFunds();
        if ($total === null) {
            $problems[] = 'The foundation\'s funds could not be read right now.';
        } elseif ($total - $this->funding->getTotalApprovedAmount() < -0.005) {
            $problems[] = 'The foundation\'s funds do not yet cover every approved amount.';
        }

        return ['ready' => $problems === [], 'problems' => $problems, 'amount' => $amount, 'channel' => $channel, 'provider' => $provider, 'payout' => $payout];
    }

    /**
     * Send the approved amount. Call only after the super admin confirmed with the emailed code.
     *
     * @throws PayoutException
     */
    public function send(string $requestId, string|int $userId): array
    {
        $request = $this->funding->getRequestById($requestId) ?? throw new PayoutException('The request was not found.');
        $check = $this->readiness($request);
        if (! $check['ready']) {
            throw new PayoutException($check['problems'][0]);
        }

        $disbursement = (array) $request['disbursement'];
        $attempt = (int) ($check['payout']['attempts'] ?? 0) + 1;
        $reference = self::REFERENCE_PREFIX . $requestId;
        $result = app(PayoutGateway::class)->send([
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
            'requested_by' => (string) $userId,
            'requested_at' => now()->toIso8601String(),
            'attempts' => $attempt,
        ];
        $this->funding->setPayout($requestId, $payout);
        $this->audit->record('funding', 'Automatic transfer sent: ₱' . number_format($check['amount'], 2) . " to {$check['provider']}", $requestId, ['payout_id' => $result['id']]);

        return $payout;
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
        $requestId = substr($reference, strlen(self::REFERENCE_PREFIX));
        $request = $this->funding->getRequestById($requestId);
        if ($request === null) {
            return false;
        }

        $payout = (array) ($request['disbursement']['payout'] ?? []);
        $status = strtoupper((string) ($data['status'] ?? ''));
        $payoutId = (string) ($data['id'] ?? $data['payout_id'] ?? $payout['payout_id'] ?? '');
        $organization = $request['org_name'] ?? 'the requester';
        $amount = (float) ($payout['amount'] ?? $request['approved_amount'] ?? 0);

        if ($status === 'SUCCEEDED') {
            if (($payout['status'] ?? null) === 'succeeded') {
                return true; // already recorded (providers may resend webhooks)
            }
            $this->funding->setPayout($requestId, array_merge($payout, ['status' => 'succeeded', 'provider_status' => $status, 'completed_at' => now()->toIso8601String()]));
            $this->funding->recordDisbursement($requestId, [
                'method' => $request['disbursement']['method'] ?? 'bank_transfer',
                'amount' => $amount,
                'released_at' => now()->toDateString(),
                'reference_no' => $payoutId,
                'notes' => 'Sent automatically through the payout provider.',
            ], 'system');
            $message = '₱' . number_format($amount, 2) . " reached {$organization}'s account. The requester was asked for the liquidation.";
            $this->notifications->notifySuperAdmins($requestId, 'payout_succeeded', 'Transfer completed', $message);
            $this->notifications->notifyAdmins($requestId, 'payout_succeeded', 'Transfer completed', $message);
            $this->audit->record('funding', 'Automatic transfer completed: ₱' . number_format($amount, 2), $requestId, ['payout_id' => $payoutId]);
        } elseif (in_array($status, ['FAILED', 'CANCELLED', 'REVERSED', 'REJECTED', 'EXPIRED', 'COMPLIANCE_REJECTED'], true)) {
            $failure = (string) ($data['failure_code'] ?? $status);
            $this->funding->setPayout($requestId, array_merge($payout, ['status' => 'failed', 'provider_status' => $status, 'failure_code' => $failure, 'completed_at' => now()->toIso8601String()]));
            $this->notifications->notifySuperAdmins($requestId, 'payout_failed', 'Transfer did not go through',
                "The transfer to {$organization} ended as {$status} ({$failure})." . ($status === 'REVERSED' ? ' It was returned after it had succeeded: check the account and the release record.' : ' Check the account details, then try again or send it manually.'));
            $this->audit->record('funding', "Automatic transfer {$status}: {$failure}", $requestId, ['payout_id' => $payoutId]);
        } else {
            $this->funding->setPayout($requestId, array_merge($payout, ['provider_status' => $status]));
        }

        return true;
    }
}
