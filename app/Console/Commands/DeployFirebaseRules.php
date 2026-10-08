<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Kreait\Firebase\Database\RuleSet;

/**
 * Publish firebase.database.rules.json to the Realtime Database, keeping a backup of the
 * rules it replaces so they can be restored with --from=<backup file>.
 */
class DeployFirebaseRules extends Command
{
    protected $signature = 'firebase:deploy-rules
        {--from= : Rules file to publish (default: firebase.database.rules.json)}
        {--dry-run : Show the current and new rules without publishing}';

    protected $description = 'Back up the live Realtime Database rules and publish the rules file';

    public function handle(FirebaseService $firebase): int
    {
        $path = $this->option('from') ?: base_path('firebase.database.rules.json');
        $rules = json_decode((string) @file_get_contents($path), true);
        if (! is_array($rules) || ! isset($rules['rules']) || ! is_array($rules['rules'])) {
            $this->error("{$path} is missing or is not a valid rules file (expected {\"rules\": {...}}).");

            return self::FAILURE;
        }

        $database = $firebase->getDatabase();
        $current = $database->getRuleSet()->getRules();

        $backupDir = storage_path('app/firebase');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backup = $backupDir . '/rules-backup-' . now()->format('Ymd-His') . '.json';
        file_put_contents($backup, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Current rules backed up to {$backup}");

        if ($this->option('dry-run')) {
            $this->line('Would publish:');
            $this->line(json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $database->updateRules(RuleSet::fromArray($rules));
        $this->info("Published {$path}. Restore with: php artisan firebase:deploy-rules --from={$backup}");

        return self::SUCCESS;
    }
}
