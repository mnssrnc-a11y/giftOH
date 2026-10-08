<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Super admin settings stored in Firebase at system_settings/*.
 */
class SystemSettings
{
    public const MIN_REQUEST_INTERVAL_DAYS = 93;

    private ?array $cache = null;

    public function __construct(
        private FirebaseService $firebase,
        private FundingRules $rules
    ) {
    }

    public function all(): array
    {
        if ($this->cache === null) {
            try {
                // Read on most funding pages; changes only when the super admin saves a setting.
                $this->cache = (array) (\Illuminate\Support\Facades\Cache::remember('system-settings', 300, fn () => $this->firebase->getDatabase()->getReference('system_settings')->getValue() ?? []) ?? []);
            } catch (\Throwable $exception) {
                Log::warning('System settings could not be read; using defaults.', ['error' => $exception->getMessage()]);
                $this->cache = [];
            }
        }

        return $this->cache;
    }

    /**
     * Priority score (0-100) per category, keyed by the category names in config/funding.php.
     */
    public function categoryPriorities(): array
    {
        $stored = (array) ($this->all()['category_priorities'] ?? []);
        $priorities = [];
        foreach ($this->rules->categories() as $name => $category) {
            $priorities[$name] = isset($stored[$name]) && is_numeric($stored[$name]) ? (float) $stored[$name] : (float) $category['priority'];
        }

        return $priorities;
    }

    public function setCategoryPriorities(array $scores): void
    {
        $clean = [];
        foreach (array_keys($this->rules->categories()) as $name) {
            if (isset($scores[$name]) && is_numeric($scores[$name])) {
                $clean[$name] = max(0, min(100, (int) $scores[$name]));
            }
        }
        $this->write('category_priorities', $clean);
    }

    /**
     * Minimum days between two requests from the same account.
     */
    public function requestIntervalDays(): int
    {
        return max(self::MIN_REQUEST_INTERVAL_DAYS, (int) ($this->all()['request_interval_days'] ?? self::MIN_REQUEST_INTERVAL_DAYS));
    }

    public function setRequestIntervalDays(int $days): void
    {
        $this->write('request_interval_days', max(self::MIN_REQUEST_INTERVAL_DAYS, $days));
    }

    /**
     * Sender chosen by the super admin when mail goes through Brevo (no .env on the server to edit).
     *
     * @return array{address: string, name: string}|null
     */
    public function mailSender(): ?array
    {
        $sender = $this->all()['mail_sender'] ?? null;

        return is_array($sender) && filter_var($sender['address'] ?? null, FILTER_VALIDATE_EMAIL)
            ? ['address' => (string) $sender['address'], 'name' => (string) ($sender['name'] ?? '')]
            : null;
    }

    public function setMailSender(string $address, string $name): void
    {
        $this->write('mail_sender', ['address' => $address, 'name' => $name]);
    }

    /**
     * Brevo API key saved by the super admin, encrypted with APP_KEY (never stored in plain text).
     * Null when none is saved, or when it was saved under a different APP_KEY.
     */
    public function brevoApiKey(): ?string
    {
        $stored = $this->all()['mail_brevo_key'] ?? null;
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            Log::warning('The saved Brevo API key cannot be decrypted (APP_KEY changed). Save it again in System settings.');

            return null;
        }
    }

    public function setBrevoApiKey(string $key): void
    {
        $this->write('mail_brevo_key', Crypt::encryptString($key));
    }

    private function write(string $key, mixed $value): void
    {
        $this->firebase->getDatabase()->getReference("system_settings/{$key}")->set($value);
        $this->cache = null;
        \Illuminate\Support\Facades\Cache::forget('system-settings');
    }
}
