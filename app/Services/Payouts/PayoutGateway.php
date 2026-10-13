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
}
