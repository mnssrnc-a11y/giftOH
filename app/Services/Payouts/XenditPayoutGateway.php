<?php

namespace App\Services\Payouts;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Xendit Payouts API (Philippines: InstaPay / PESONet to banks, and e-wallets such as GCash).
 *
 *   POST /v2/payouts            send (Idempotency-key header: a retried request never pays twice)
 *   GET  /v2/payouts/{id}       status of a transfer
 *   GET  /balance               money in the Xendit cash account
 *   GET  /payouts_channels      banks and e-wallets the account can send to
 *
 * HTTP Basic auth with the secret key as the user name. The final result also arrives by webhook
 * (see PayoutService::handleWebhook). A test key (xnd_development_...) simulates transfers:
 * any account succeeds except Xendit's test numbers (e.g. 999999 is rejected by the channel).
 */
class XenditPayoutGateway implements PayoutGateway
{
    public function send(array $transfer): array
    {
        $response = $this->call(fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-key' => $transfer['idempotency_key']])
            ->post('/v2/payouts', [
                'reference_id' => $transfer['reference_id'],
                'channel_code' => $transfer['channel_code'],
                'channel_properties' => [
                    'account_holder_name' => $transfer['account_name'],
                    'account_number' => $transfer['account_number'],
                ],
                'amount' => round($transfer['amount'], 2),
                'currency' => 'PHP',
                'description' => mb_substr($transfer['description'], 0, 50),
            ]), 'send the transfer');

        return [
            'id' => (string) ($response->json('id') ?? $response->json('payout_id') ?? ''),
            'status' => strtoupper((string) ($response->json('status') ?? 'ACCEPTED')),
        ];
    }

    public function status(string $payoutId): array
    {
        $response = $this->call(fn (PendingRequest $http) => $http->get('/v2/payouts/' . rawurlencode($payoutId)), 'read the transfer');

        return [
            'id' => (string) ($response->json('id') ?? $payoutId),
            'status' => strtoupper((string) $response->json('status')),
            'failure_code' => $response->json('failure_code'),
        ];
    }

    public function balance(): ?float
    {
        try {
            $response = $this->call(fn (PendingRequest $http) => $http->get('/balance', ['account_type' => 'CASH', 'currency' => 'PHP']), 'read the balance');
        } catch (PayoutException $exception) {
            report($exception);

            return null;
        }

        return is_numeric($response->json('balance')) ? (float) $response->json('balance') : null;
    }

    public function channels(): array
    {
        $response = $this->call(fn (PendingRequest $http) => $http->get('/payouts_channels', ['currency' => 'PHP']), 'list the channels');

        return collect((array) $response->json())
            // Philippine channels with a proper code (the list has had entries with code and name swapped).
            ->filter(fn ($channel) => is_array($channel) && ($channel['currency'] ?? 'PHP') === 'PHP' && preg_match('/^[A-Z0-9_]+$/', (string) ($channel['channel_code'] ?? '')))
            ->map(fn (array $channel) => [
                'code' => (string) $channel['channel_code'],
                'name' => (string) ($channel['channel_name'] ?? $channel['channel_code']),
                'category' => (string) ($channel['channel_category'] ?? ''),
                'minimum' => isset($channel['amount_limits']['minimum']) ? (float) $channel['amount_limits']['minimum'] : null,
                'maximum' => isset($channel['amount_limits']['maximum']) ? (float) $channel['amount_limits']['maximum'] : null,
            ])
            ->sortBy('name')->values()->all();
    }

    public function mode(): string
    {
        return str_starts_with((string) config('payouts.xendit.secret_key'), 'xnd_production_') ? 'live' : 'test';
    }

    /** @param  callable(PendingRequest): Response  $request */
    private function call(callable $request, string $what): Response
    {
        $config = config('payouts.xendit');
        if (blank($config['secret_key'] ?? null)) {
            throw new PayoutException('XENDIT_SECRET_KEY is not set.');
        }

        try {
            $response = $request(Http::baseUrl(rtrim((string) $config['base_url'], '/'))
                ->timeout((int) ($config['timeout'] ?? 30))
                ->withBasicAuth((string) $config['secret_key'], '')
                ->acceptJson());
        } catch (\Throwable $exception) {
            throw new PayoutException('Could not reach Xendit: ' . $exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            $code = $response->json('error_code') ?? $response->status();
            throw new PayoutException("Xendit could not {$what} ({$code}): " . ($response->json('message') ?? 'no details'));
        }

        return $response;
    }
}
