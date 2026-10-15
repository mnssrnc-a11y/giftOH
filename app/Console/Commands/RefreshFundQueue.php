<?php

namespace App\Console\Commands;

use App\Services\FundQueue;
use Illuminate\Console\Command;

/**
 * New donations arrive from the smart boxes without the app knowing: check the release line every
 * few minutes and tell the admins when the next request's money is ready to transfer.
 */
class RefreshFundQueue extends Command
{
    protected $signature = 'funds:queue';

    protected $description = 'Focus the donations on hand on approved requests (first come, first served) and announce the ones ready to transfer';

    public function handle(FundQueue $queue): int
    {
        $announced = $queue->refresh();
        $state = $queue->state();
        $this->info(count($state['rows']) . ' request(s) in line · ₱' . number_format((float) $state['balance'], 2) . ' on hand · '
            . count($announced) . ' newly ready to transfer.');

        return self::SUCCESS;
    }
}
