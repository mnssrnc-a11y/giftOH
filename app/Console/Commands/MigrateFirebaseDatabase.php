<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kreait\Firebase\Factory;
use Throwable;

class MigrateFirebaseDatabase extends Command
{
    protected $signature = 'firebase:migrate-database
        {--source-credentials=storage/app/firebase/firebase_credentials.json : Source service-account JSON}
        {--source-url=https://giftofhope-17667-default-rtdb.firebaseio.com : Source Realtime Database URL}
        {--destination-credentials=storage/app/firebase/githope-d36ee-firebase-adminsdk-fbsvc-3664797c75.json : Destination service-account JSON}
        {--destination-url=https://githope-d36ee-default-rtdb.asia-southeast1.firebasedatabase.app : Destination Realtime Database URL}
        {--write : Copy the source root to the destination; without this option the command is read-only}';

    protected $description = 'Copy the complete Realtime Database root between Firebase projects';

    public function handle(): int
    {
        try {
            $source = $this->database(
                (string) $this->option('source-credentials'),
                (string) $this->option('source-url')
            );
            $destination = $this->database(
                (string) $this->option('destination-credentials'),
                (string) $this->option('destination-url')
            );

            $sourceValue = $source->getReference('/')->getValue();
            $destinationValue = $destination->getReference('/')->getValue();
            $sourceNodes = is_array($sourceValue) ? array_keys($sourceValue) : [];
            $destinationNodes = is_array($destinationValue) ? array_keys($destinationValue) : [];

            $this->line('Source nodes: ' . ($sourceNodes === [] ? '(empty)' : implode(', ', $sourceNodes)));
            $this->line('Destination nodes: ' . ($destinationNodes === [] ? '(empty)' : implode(', ', $destinationNodes)));

            if (! $this->option('write')) {
                $this->info('Dry run complete. No destination data was changed.');

                return self::SUCCESS;
            }

            if (! is_array($sourceValue)) {
                $this->error('The source database root is empty or is not a JSON object.');

                return self::FAILURE;
            }

            $destination->getReference('/')->update($sourceValue);
            $this->info('Firebase Realtime Database migration completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function database(string $credentials, string $url): mixed
    {
        if (! is_file($credentials)) {
            throw new \RuntimeException("Credential file was not found: {$credentials}");
        }

        return (new Factory)
            ->withServiceAccount($credentials)
            ->withDatabaseUri($url)
            ->createDatabase();
    }
}