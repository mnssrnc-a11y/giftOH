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

    /**
     * The service-account JSON from an environment variable, raw or base64. Hosting dashboards
     * often damage long pasted values (wrapping quotes, line breaks inside the base64, real line
     * breaks inside the JSON), so those are repaired. Errors say what is wrong, never the value.
     */
    public static function inlineCredentials(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        // Quotes kept from a .env paste: "...", '...'.
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && $value[-1] === $value[0]) {
            $value = trim(substr($value, 1, -1));
        }
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            // Real line breaks inside the private key (a pasted file whose "\n" turned into breaks).
            $decoded ??= json_decode((string) preg_replace_callback('/("private_key"\s*:\s*")(.*?)(")/s',
                fn (array $m): string => $m[1] . preg_replace('/\r?\n/', '\\n', $m[2]) . $m[3], $value), true);
            $problem = is_array($decoded) ? null : 'it starts with "{" but is not valid JSON (' . json_last_error_msg() . ')';
        } else {
            $base64 = preg_replace('/\s+/', '', $value);
            $json = base64_decode($base64, true);
            $decoded = is_string($json) ? json_decode($json, true) : null;
            $problem = match (true) {
                str_contains($value, '/') && str_ends_with(strtolower($value), '.json') => 'it looks like a file path; paste the file contents (or its base64) instead',
                $json === false => 'it is neither JSON nor valid base64 (' . strlen($base64) . ' characters; was it cut off when pasted?)',
                ! is_array($decoded) => 'the base64 decodes to something that is not JSON (' . strlen($base64) . ' characters; was it cut off when pasted?)',
                default => null,
            };
        }

        if ($problem === null && ! isset($decoded['private_key'], $decoded['client_email'])) {
            $problem = 'the JSON has no private_key/client_email, so it is not a service-account key';
        }
        if ($problem !== null) {
            throw new \RuntimeException("FIREBASE_CREDENTIALS_JSON is not a valid service-account key: {$problem}. Paste the value from storage/app/firebase/render.env again.");
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
