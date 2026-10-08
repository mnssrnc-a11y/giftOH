<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Services\PriceListService;
use App\Services\PriceUpdateRunner;
use App\Services\PriceUpdateService;
use Illuminate\Console\Command;

class UpdatePrices extends Command
{
    protected $signature = 'prices:update
        {--dry-run : Find prices but do not change the price list}
        {--claimed : Started by the super admin\'s "Update prices now" (the run is already claimed)}';

    protected $description = 'Update the price list from the DTI SRP bulletin, the TGP store and AI web search (runs monthly)';

    public function handle(PriceUpdateService $updater, PriceUpdateRunner $runner, PriceListService $prices, NotificationService $notifications): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('claimed') && ! $runner->claim()) {
            $this->warn('A price update is already running.');

            return self::FAILURE;
        }

        $this->info('Reading DTI SRP bulletin, TGP store and web search… this can take a few minutes.');
        try {
            $summary = $updater->run($dryRun);
        } catch (\Throwable $exception) {
            report($exception);
            $message = 'The price update stopped: ' . mb_strimwidth($exception->getMessage(), 0, 200, '…');
            if (! $dryRun) {
                $prices->recordRun(['failed' => $message, 'updated' => [], 'not_found' => [], 'flagged' => []]);
                $runner->finish('failed', $message);
                $notifications->notifyRole('super_admin', 'price_update', 'Price update failed', $message, route('superadmin', [], false) . '#prices');
            }
            $this->error($message);

            return self::FAILURE;
        }

        foreach ($summary['sources'] as $name => $source) {
            $this->line(sprintf('  %-6s %s%s', strtoupper($name), $source['status'] ?? '-', isset($source['matched']) ? " · {$source['matched']} matched" : ''));
        }
        $result = sprintf('%d price(s) %s, %d checked, %d not found, %d flagged for review.',
            count($summary['updated']), $dryRun ? 'would change' : 'changed', $summary['checked'], count($summary['not_found']), count($summary['flagged']));
        $this->info($result);
        foreach ($summary['flagged'] as $flag) {
            $this->warn("  {$flag['name']}: {$flag['reason']}");
        }

        if (! $dryRun) {
            $runner->finish('done', $result, count($summary['updated']));
            $notifications->notifyRole('super_admin', 'price_update', 'Price update finished', $result, route('superadmin', [], false) . '#prices');
        }

        return self::SUCCESS;
    }
}
