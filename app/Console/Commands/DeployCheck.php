<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;

/**
 * Plain-language check of the live site's settings, printed to the host's log at every start
 * (docker/entrypoint.sh). It never prints a secret, only whether each one is usable.
 */
class DeployCheck extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Check the deployment settings (Firebase key, app key, address, storage, email) without showing secrets';

    private int $errors = 0;

    public function handle(): int
    {
        $this->report(filled(config('app.key')) && str_starts_with((string) config('app.key'), 'base64:'), 'APP_KEY is set', 'APP_KEY is missing or does not start with "base64:". Paste render.env again.');

        $url = (string) config('app.url');
        $this->report(str_starts_with($url, 'https://') && ! str_contains($url, 'localhost'), "APP_URL is {$url}",
            "APP_URL is \"{$url}\"; set it to the site's https:// address (for example https://giftoh.onrender.com).", warnOnly: true);

        $this->firebaseKey();
        $this->report(filled(config('services.firebase.database_url')), 'FIREBASE_DATABASE_URL is set', 'FIREBASE_DATABASE_URL is missing.');

        $this->report(getenv('FILES_DRIVER') === 'firebase', 'Uploads are kept in Firebase (FILES_DRIVER=firebase)',
            'FILES_DRIVER is not "firebase": uploads are lost whenever the service restarts.', warnOnly: true);
        $this->report(getenv('SESSION_DRIVER') === 'cookie', 'Sign-ins survive restarts (SESSION_DRIVER=cookie)',
            'SESSION_DRIVER is not "cookie": everyone is signed out whenever the service restarts.', warnOnly: true);

        $mailer = (string) config('mail.default');
        $this->line('deploy check: email goes through ' . ($mailer === 'smtp' && blank(config('mail.mailers.smtp.password')) ? 'Brevo' : $mailer)
            . (filled(config('mail.mailers.brevo.key')) ? ' (BREVO_API_KEY set)' : ' (key saved by the super admin in System settings, if any)'));

        $this->line($this->errors ? "deploy check: {$this->errors} problem(s) above will stop the site from working." : 'deploy check: settings look complete.');

        return self::SUCCESS;
    }

    private function firebaseKey(): void
    {
        try {
            $inline = FirebaseService::inlineCredentials(config('services.firebase.credentials_json'));
        } catch (\RuntimeException $exception) {
            $this->report(false, '', $exception->getMessage());

            return;
        }

        if ($inline !== null) {
            $this->report(true, 'Firebase key read from FIREBASE_CREDENTIALS_JSON (' . ($inline['project_id'] ?? 'project') . ')', '');

            return;
        }

        $path = (string) config('services.firebase.credentials');
        $full = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
        $json = is_file($full) ? json_decode((string) file_get_contents($full), true) : null;
        $this->report(is_array($json) && isset($json['private_key'], $json['client_email']),
            "Firebase key read from the file {$path}",
            is_file($full)
                ? "The Firebase key file {$path} is not a valid service-account key."
                : "No Firebase key: FIREBASE_CREDENTIALS_JSON is empty and the file \"{$path}\" does not exist on the server. Paste render.env, or add a Secret File and point FIREBASE_CREDENTIALS at it.");
    }

    private function report(bool $ok, string $good, string $bad, bool $warnOnly = false): void
    {
        if ($ok) {
            $this->line("deploy check: OK - {$good}");

            return;
        }
        if (! $warnOnly) {
            $this->errors++;
        }
        $this->line('deploy check: ' . ($warnOnly ? 'WARNING' : 'ERROR') . " - {$bad}");
    }
}
