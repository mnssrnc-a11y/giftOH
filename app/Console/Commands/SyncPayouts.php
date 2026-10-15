<?php

namespace App\Console\Commands;

use App\Services\Payouts\PayoutService;
use Illuminate\Console\Command;

/**
 * Transfers in progress: ask the payout provider how they ended, for when its webhook did not
 * arrive (for example on a PC the provider cannot reach).
 */
class SyncPayouts extends Command
{
    protected $signature = 'payouts:sync';

    protected $description = 'Check the transfers still in progress with the payout provider and record the ones that ended';

    public function handle(PayoutService $payouts): int
    {
        if (! $payouts->enabled()) {
            $this->info('Automatic transfers are not connected (PAYOUT_DRIVER=' . config('payouts.driver') . ').');

            return self::SUCCESS;
        }

        $this->info($payouts->syncAll() . ' transfer(s) checked · ' . $payouts->modeLabel($payouts->mode()) . '.');

        return self::SUCCESS;
    }
}
