<?php

namespace App\Services;

use App\Support\Cache\LaravelCachePool;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;
use Kreait\Firebase\Messaging;

class FirebaseService
{
    protected Database $database;
    protected Messaging $messaging;

    public function __construct()
    {
        $credentials = config('services.firebase.credentials',
        storage_path('app/firebase/firebase_credentials.json'));
        $databaseUrl = config('services.firebase.database_url');

        if (is_string($credentials)
            && ! str_starts_with($credentials, DIRECTORY_SEPARATOR)
            && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $credentials)) {
            $credentials = base_path($credentials);
        }

        // FIREBASE_CREDENTIALS_JSON (raw JSON or base64) wins over a file path, for hosts without files.
        if ($inline = self::inlineCredentials(config('services.firebase.credentials_json'))) {
            $credentials = $inline;
        } elseif (! is_string($credentials) || ! is_file($credentials)) {
            throw new \RuntimeException("Firebase credentials file was not found: {$credentials}");
        }

        if (! is_string($databaseUrl) || $databaseUrl === '') {
            throw new \RuntimeException('FIREBASE_DATABASE_URL is not configured.');
        }

        // Google access tokens last an hour; keeping them in the app cache saves a ~0.8 s sign-in
        // to Google on every page.
        $factory = (new Factory)
            ->withServiceAccount($credentials)
            ->withDatabaseUri($databaseUrl)
            ->withAuthTokenCache(new LaravelCachePool(app('cache')->store(), 'firebase-auth:'));

        $this->database = $factory->createDatabase();
        $this->messaging = $factory->createMessaging();
    }

    private static function inlineCredentials(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $json = str_starts_with(ltrim($value), '{') ? $value : base64_decode(trim($value), true);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($decoded) || ! isset($decoded['private_key'], $decoded['client_email'])) {
            throw new \RuntimeException('FIREBASE_CREDENTIALS_JSON is not a valid service-account JSON (raw or base64).');
        }

        return $decoded;
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
