<?php

namespace App\Console\Commands;

use App\Services\Ai\AiClient;
use Illuminate\Console\Command;

class AiStatus extends Command
{
    protected $signature = 'ai:status {--ping : Send a test request to every configured provider}';

    protected $description = 'Show which free AI providers are configured, their usage today, and (with --ping) whether they answer';

    public function handle(AiClient $ai): int
    {
        $rows = [];
        foreach ($ai->status() as $provider) {
            $row = [
                $provider['name'],
                $provider['model'],
                $provider['configured'] ? ($provider['unavailable'] ?? 'ready') : 'no API key',
                $provider['used_today'] . ($provider['daily_limit'] ? " / {$provider['daily_limit']}" : ''),
            ];

            if ($this->option('ping')) {
                $result = $provider['configured'] ? $ai->ping($provider['name']) : ['ok' => false, 'message' => '—', 'ms' => 0];
                $row[] = ($result['ok'] ? 'OK ' : 'FAIL ') . "{$result['ms']}ms " . mb_substr($result['message'], 0, 60);
            }

            $rows[] = $row;
        }

        $this->table(array_merge(['Provider', 'Model', 'Status', 'Used today'], $this->option('ping') ? ['Ping'] : []), $rows);
        $this->line('Order: ' . implode(' → ', config('ai.order')) . ' · strategy: ' . config('ai.strategy'));

        if (! $ai->hasProvider()) {
            $this->warn('No provider is configured. Add at least one free API key to .env (see config/ai.php for sign-up links).');
        }

        return self::SUCCESS;
    }
}
