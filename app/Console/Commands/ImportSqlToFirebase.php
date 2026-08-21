<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

class ImportSqlToFirebase extends Command
{
    protected $signature = 'firebase:import
        {--table=* : Import only these SQL table names}
        {--limit= : Limit rows per table}
        {--write : Actually write records to Firebase; without this option the command is a dry run}';

    protected $description = 'Import SQL records to Firebase without deleting the SQL source';

    private array $tables = [
        'users' => ['node' => 'users', 'key' => 'id'],
        'roles' => ['node' => 'roles', 'key' => 'id'],
        'permissions' => ['node' => 'permissions', 'key' => 'id'],
        'role_permission' => ['node' => 'role_permissions', 'key' => null],
        'request_status_tb' => ['node' => 'request_statuses', 'key' => 'status_id'],
        'funding_categories_tb' => ['node' => 'funding_categories', 'key' => 'category_id'],
        'funding_request_tb' => ['node' => 'funding_requests', 'key' => 'id'],
        'funding_documents' => ['node' => 'funding_documents', 'key' => 'document_id'],
        'funding_approvals' => ['node' => 'funding_approvals', 'key' => 'approval_id'],
        'funding_appeals' => ['node' => 'funding_appeals', 'key' => 'appeal_id'],
        'donations' => ['node' => 'donations', 'key' => 'donation_id'],
        'beneficiary_profiles' => ['node' => 'beneficiary_profiles', 'key' => 'beneficiary_id'],
        'iot_boxes' => ['node' => 'iot_boxes', 'key' => 'iot_id'],
        'audit_logs' => ['node' => 'audit_logs', 'key' => 'log_id'],
        'activity_logs' => ['node' => 'activity_logs', 'key' => 'id'],
        'notification_logs' => ['node' => 'notification_logs', 'key' => 'notification_id'],
    ];

    public function handle(FirebaseService $firebase): int
    {
        $selected = $this->option('table');
        $tables = $selected === []
            ? array_keys($this->tables)
            : array_values(array_intersect($selected, array_keys($this->tables)));

        $unknown = array_diff($selected, array_keys($this->tables));
        if ($unknown !== []) {
            $this->error('Unknown table(s): ' . implode(', ', $unknown));
            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $write = (bool) $this->option('write');
        $database = $firebase->getDatabase();
        $total = 0;

        $this->info($write ? 'IMPORT MODE: writing to Firebase; SQL remains unchanged.' : 'DRY RUN: no Firebase data will be changed.');

        foreach ($tables as $table) {
            try {
                $query = DB::table($table);
                if ($limit !== null && $limit > 0) {
                    $query->limit($limit);
                }

                $rows = $query->get();
                $mapping = $this->tables[$table];
                $count = 0;

                foreach ($rows as $row) {
                    $record = $this->normalize((array) $row);
                    $key = $this->recordKey($table, $record, $mapping['key']);

                    if ($write) {
                        $database->getReference($mapping['node'] . '/' . $key)->set($record);
                    }

                    $count++;
                }

                $total += $count;
                $this->line(sprintf('%-32s %d record(s)', $table, $count));
            } catch (Throwable $exception) {
                $this->error("{$table}: {$exception->getMessage()}");
                return self::FAILURE;
            }
        }

        $this->newLine();

        if ($total === 0) {
            $this->error('No SQL records were found. Restore or select the database containing your migrated data before using --write.');

            return self::FAILURE;
        }

        $this->info("Processed {$total} record(s).");
        return self::SUCCESS;
    }

    private function recordKey(string $table, array $record, ?string $primaryKey): string
    {
        if ($primaryKey !== null && isset($record[$primaryKey])) {
            return (string) $record[$primaryKey];
        }

        if ($table === 'role_permission') {
            return (string) ($record['role_id'] . '_' . $record['permission_id']);
        }

        throw new \UnexpectedValueException("No Firebase key could be determined for {$table}.");
    }

    private function normalize(array $record): array
    {
        foreach ($record as $field => $value) {
            if ($value instanceof \DateTimeInterface) {
                $record[$field] = $value->format(DATE_ATOM);
                continue;
            }

            if (is_string($value) && in_array($field, [
                'changes',
                'required_documents',
                'ai_scoring_details',
                'ai_score_breakdown',
            ], true)) {
                try {
                    $record[$field] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    // Keep malformed legacy JSON as its original string for review.
                }
            }
        }

        return $record;
    }
}
