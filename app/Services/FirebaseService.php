<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;
use Kreait\Firebase\Messaging;

class FirebaseService
{
    protected Database $database;
    protected Messaging $messaging;

    public function __construct()
    {
        $credentials = config('services.firebase_credentials',
        storage_path('app/firebase/firebase_credentials.json'));
        $databaseUrl = config('services.firebase.database_url');

        if (! is_string($credentials) || ! is_file($credentials)) {
            throw new \RuntimeException("Firebase credentials file was not found: {$credentials}");
        }

        if (! is_string($databaseUrl) || $databaseUrl === '') {
            throw new \RuntimeException('FIREBASE_DATABASE_URL is not configured.');
        }

        $factory = (new Factory)
            ->withServiceAccount($credentials)
            ->withDatabaseUri($databaseUrl);

        $this->database = $factory->createDatabase();
        $this->messaging = $factory->createMessaging();
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getMessaging(): Messaging
    {
        return $this->messaging;
    }
}
