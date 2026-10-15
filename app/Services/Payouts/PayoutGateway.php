<?php

namespace App\Services\Payouts;

/**
 * Sends money from the foundation's payout balance to a receiver's bank account or e-wallet.
 */
interface PayoutGateway
{
    /**
     * @param  array{reference_id: string, idempotency_key: string, channel_code: string, account_number: string, account_name: string, amount: float, description: string}  $transfer
     * @return array{id: string, status: string} the provider's payout id and status
     *
     * @throws PayoutException when the provider refuses the transfer
     */
    public function send(array $transfer): array;

    /**
     * Current state of a transfer, for when the webhook did not arrive (or cannot reach this PC).
     *
     * @return array{id: string, status: string, failure_code: ?string}
     *
     * @throws PayoutException
     */
    public function status(string $payoutId): array;

    /**
     * Money available in the payout account (PHP), or null when it cannot be read.
     */
    public function balance(): ?float;

    /**
     * Banks and e-wallets this account can send to.
     *
     * @return array<int, array{code: string, name: string, category: string, minimum: ?float, maximum: ?float}>
     *
     * @throws PayoutException
     */
    public function channels(): array;

    /** "live", "test" or "sandbox": whether real money moves. */
    public function mode(): string;
}
