<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes the environment variables for the online deployment (Render free plan) from this
 * computer's .env, so they can be pasted into Render → Environment → "Add from .env".
 *
 *     php artisan deploy:render-env --url=https://giftofhope.onrender.com --brevo-key=xkeysib-...
 *
 * The output file holds secrets (APP_KEY, Firebase key, API keys). It is written next to the
 * Firebase key in storage/app/firebase, which Git and the Docker image both ignore. Delete it
 * after pasting.
 */
class RenderEnv extends Command
{
    protected $signature = 'deploy:render-env
        {--url= : Public address of the site, e.g. https://giftofhope.onrender.com}
        {--brevo-key= : Brevo API key (Brevo → SMTP & API → API keys)}
        {--sender= : Sender address verified in Brevo (defaults to MAIL_FROM_ADDRESS)}
        {--output=storage/app/firebase/render.env : Where to write the variables}';

    protected $description = 'Write the Render environment variables (production settings + your keys) to paste into Render';

    /** Only meaningful on this computer, or replaced below. */
    private const DROP = [
        'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_MAINTENANCE_DRIVER',
        'LOG_CHANNEL', 'LOG_STACK', 'LOG_LEVEL', 'LOG_DEPRECATIONS_CHANNEL',
        'FIREBASE_CREDENTIALS', 'SESSION_DRIVER', 'SESSION_DOMAIN', 'SESSION_SECURE_COOKIE',
        'FILESYSTEM_DISK', 'FILES_DRIVER', 'CACHE_STORE', 'QUEUE_CONNECTION', 'BROADCAST_CONNECTION',
        'MEMCACHED_HOST', 'REDIS_CLIENT', 'REDIS_HOST', 'REDIS_PASSWORD', 'REDIS_PORT',
        'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_SCHEME',
        'UPLOAD_SCAN_ENGINES', 'DEFENDER_MPCMDRUN_PATH',
        'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET', 'AWS_USE_PATH_STYLE_ENDPOINT',
        'VITE_APP_NAME',
    ];

    public function handle(): int
    {
        $envFile = base_path('.env');
        if (! is_file($envFile)) {
            $this->error('No .env file found in the project folder.');

            return self::FAILURE;
        }

        $local = $this->parse(File::get($envFile));
        $url = rtrim((string) ($this->option('url') ?: 'https://giftofhope.onrender.com'), '/');

        $credentialsPath = $local['FIREBASE_CREDENTIALS'] ?? '';
        if ($credentialsPath !== '' && ! preg_match('#^([A-Za-z]:[\\\\/]|/)#', $credentialsPath)) {
            $credentialsPath = base_path($credentialsPath);
        }
        if (! is_file($credentialsPath)) {
            $this->error("Firebase key not found at {$credentialsPath} (FIREBASE_CREDENTIALS in .env).");

            return self::FAILURE;
        }

        $production = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'warning',
            // The free plan's disk is wiped on every restart: sessions live in an encrypted cookie,
            // uploads in Firebase, mail goes over HTTPS.
            'SESSION_DRIVER' => 'cookie',
            'SESSION_SECURE_COOKIE' => 'true',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'FILES_DRIVER' => 'firebase',
            'MAIL_MAILER' => 'brevo',
            'BREVO_API_KEY' => (string) ($this->option('brevo-key') ?: ''),
            'MAIL_FROM_ADDRESS' => (string) ($this->option('sender') ?: ($local['MAIL_FROM_ADDRESS'] ?? '')),
            // Windows Defender is not on a Linux server; the built-in checks still run on every upload.
            'UPLOAD_SCAN_ENGINES' => 'heuristic',
            'FIREBASE_CREDENTIALS_JSON' => base64_encode(File::get($credentialsPath)),
        ];

        $lines = ['# Gift of Hope — Render environment. Paste into Render → Environment → Add from .env.', '# Contains secrets: delete this file after pasting.'];
        foreach ($local as $key => $value) {
            if (in_array($key, self::DROP, true) || array_key_exists($key, $production) || $value === '') {
                continue;
            }
            $lines[] = $key . '=' . $this->quote($value);
        }
        foreach ($production as $key => $value) {
            $lines[] = $key . '=' . $this->quote($value);
        }

        $output = base_path((string) $this->option('output'));
        File::ensureDirectoryExists(dirname($output));
        File::put($output, implode(PHP_EOL, $lines) . PHP_EOL);

        $this->info("Wrote {$output}");
        if ($production['BREVO_API_KEY'] === '') {
            $this->warn('BREVO_API_KEY is empty: add it (--brevo-key=...) or no emails will be sent.');
        }
        $this->line('Paste the file into Render → your service → Environment → Add from .env, then delete it.');

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private function parse(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\R/', $contents) as $line) {
            if (! preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $match)) {
                continue;
            }
            $value = trim($match[2]);
            if (preg_match('/^"(.*)"$/s', $value, $quoted)) {
                $value = stripcslashes($quoted[1]);
            } elseif (preg_match("/^'(.*)'$/s", $value, $quoted)) {
                $value = $quoted[1];
            } else {
                $value = trim(preg_replace('/\s+#.*$/', '', $value));
            }
            $values[$match[1]] = $value;
        }

        return $values;
    }

    private function quote(string $value): string
    {
        return preg_match('/[\s#"\'=$]/', $value) ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"' : $value;
    }
}
