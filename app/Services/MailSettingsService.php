<?php

namespace App\Services;

use App\Mail\MailSettingsCode;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * The super admin's "Verification email settings": the Brevo API key and the sender address of
 * verification codes and notices. New settings are proven by sending a code with them; they are
 * saved (the key encrypted, in Firebase) only after that code is confirmed.
 *
 * Gmail SMTP (MAIL_MAILER=smtp with an app password in .env) still works; without the password
 * the app uses Brevo.
 */
class MailSettingsService
{
    private const SESSION_KEY = 'pending_mail_settings';

    public function __construct(
        private EnvironmentFile $env,
        private SystemSettings $settings
    ) {
    }

    /** True when mail goes through Brevo's HTTPS API (the default). */
    public function usesBrevo(): bool
    {
        return config('mail.default') === 'brevo';
    }

    /** The Brevo API key in use: the one the super admin saved, else BREVO_API_KEY. */
    public function brevoKey(): ?string
    {
        try {
            $saved = $this->settings->brevoApiKey();
        } catch (\Throwable $exception) {
            report($exception);
            $saved = null;
        }

        return $saved ?: (filled(config('mail.mailers.brevo.key')) ? (string) config('mail.mailers.brevo.key') : null);
    }

    /**
     * Whether the app can send email at all: Brevo needs an API key, SMTP with a login needs its
     * password. Dev mailers (log, array) always "send". When this is false, registration skips the
     * emailed code and sign-in codes are not required, so nobody gets locked out.
     */
    public function canSend(): bool
    {
        return match (config('mail.default')) {
            'smtp' => filled(config('mail.mailers.smtp.host'))
                && (blank(config('mail.mailers.smtp.username')) || filled(config('mail.mailers.smtp.password'))),
            'brevo' => filled($this->brevoKey()),
            default => true,
        };
    }

    public function current(): array
    {
        if ($this->usesBrevo()) {
            $sender = $this->settings->mailSender();
            $key = $this->brevoKey();

            return [
                'driver' => 'brevo',
                'host' => 'Brevo API',
                'port' => 'HTTPS',
                'username' => null,
                'from_address' => $sender['address'] ?? config('mail.from.address'),
                'from_name' => $sender['name'] ?? config('mail.from.name'),
                'password_set' => filled($key),
                // Only the last characters are ever shown.
                'key_hint' => $key ? '…' . substr($key, -4) : null,
            ];
        }

        return [
            'driver' => 'smtp',
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'password_set' => filled(config('mail.mailers.smtp.password')),
            'key_hint' => null,
        ];
    }

    /**
     * Send a code to the sender address using the new settings. With Brevo, $secret is a new API
     * key (null keeps the saved one); with SMTP it is the Gmail app password. Throws when Brevo or
     * the mail server rejects them.
     */
    public function startChange(string $email, string $appName, ?string $secret): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($this->usesBrevo()) {
            $key = $secret ?: $this->brevoKey();
            if (! $key) {
                throw new \InvalidArgumentException('Enter the Brevo API key.');
            }
            // Brevo refuses a wrong key and senders that are not verified in the account.
            Mail::build(['transport' => 'brevo', 'api_key' => $key, 'timeout' => 20])
                ->to($email)->send(new MailSettingsCode($code, $email, $appName));
        } else {
            Mail::build([
                'transport' => 'smtp',
                'host' => config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                'port' => config('mail.mailers.smtp.port', 587),
                'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                'username' => $email,
                'password' => $secret,
                'timeout' => 20,
            ])->to($email)->send(new MailSettingsCode($code, $email, $appName));
        }

        session([self::SESSION_KEY => [
            'email' => $email,
            'app_name' => $appName,
            'secret' => $secret === null || $secret === '' ? null : Crypt::encryptString($secret),
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
        ]]);
    }

    public function pending(): ?array
    {
        $pending = session(self::SESSION_KEY);

        return $pending && $pending['expires_at'] > now()->timestamp
            ? ['email' => $pending['email'], 'app_name' => $pending['app_name'], 'new_key' => $pending['secret'] !== null]
            : null;
    }

    /**
     * @return string|null error message, or null when saved
     */
    public function confirm(string $code): ?string
    {
        $pending = session(self::SESSION_KEY);
        if (! $pending || $pending['expires_at'] <= now()->timestamp) {
            session()->forget(self::SESSION_KEY);

            return 'The code expired. Enter the new email settings again.';
        }
        if ($pending['attempts'] >= 5) {
            session()->forget(self::SESSION_KEY);

            return 'Too many wrong codes. Enter the new email settings again.';
        }
        if (! Hash::check($code, $pending['code'])) {
            session([self::SESSION_KEY . '.attempts' => $pending['attempts'] + 1]);

            return 'That code is not correct.';
        }

        $secret = $pending['secret'] !== null ? Crypt::decryptString($pending['secret']) : null;

        if ($this->usesBrevo()) {
            if ($secret !== null) {
                $this->settings->setBrevoApiKey($secret);
            }
            $this->settings->setMailSender($pending['email'], $pending['app_name']);
            session()->forget(self::SESSION_KEY);

            return null;
        }

        $this->env->set([
            'MAIL_USERNAME' => $pending['email'],
            'MAIL_PASSWORD' => (string) $secret,
            'MAIL_FROM_ADDRESS' => $pending['email'],
            'MAIL_FROM_NAME' => $pending['app_name'],
        ]);

        // Use the new settings for the rest of this request too.
        config([
            'mail.mailers.smtp.username' => $pending['email'],
            'mail.mailers.smtp.password' => $secret,
            'mail.from.address' => $pending['email'],
            'mail.from.name' => $pending['app_name'],
        ]);
        session()->forget(self::SESSION_KEY);

        return null;
    }

    public function cancel(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
