<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Http;

/**
 * Xendit Payouts API (Philippines: InstaPay / PESONet to banks, and e-wallets such as GCash).
 *
 * POST {base_url}/v2/payouts, HTTP Basic auth with the secret key as the user name, and an
 * Idempotency-key header so a retried request never pays twice. The final result arrives by
 * webhook (see PayoutService::handleWebhook). Confirm the request format against Xendit's API
 * reference in test mode before using a live key.
 */
class XenditPayoutGateway implements PayoutGateway
{
    public function send(array $transfer): array
    {
        $config = config('payouts.xendit');
        if (blank($config['secret_key'] ?? null)) {
            throw new PayoutException('XENDIT_SECRET_KEY is not set.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) $config['base_url'], '/'))
                ->timeout((int) ($config['timeout'] ?? 30))
                ->withBasicAuth((string) $config['secret_key'], '')
                ->withHeaders(['Idempotency-key' => $transfer['idempotency_key']])
                ->acceptJson()
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
                ]);
        } catch (\Throwable $exception) {
            throw new PayoutException('Could not reach Xendit: ' . $exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            $code = $response->json('error_code') ?? $response->status();
            throw new PayoutException("Xendit refused the transfer ({$code}): " . ($response->json('message') ?? 'no details'));
        }

        return [
            'id' => (string) ($response->json('id') ?? $response->json('payout_id') ?? ''),
            'status' => strtoupper((string) ($response->json('status') ?? 'ACCEPTED')),
        ];
    }
}
