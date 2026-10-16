<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * .env.example is committed to GitHub: it lists the settings, never their secret values. Real keys
 * go only in .env (ignored by git) and in Render's Environment settings.
 */
class EnvExampleTest extends TestCase
{
    public function test_the_example_settings_hold_no_secrets(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        // Recognisable live or test keys.
        foreach ([
            'Xendit key' => '/xnd_(development|production)_[A-Za-z0-9]{10,}/',
            'Brevo key' => '/xkeysib-[a-f0-9]{20,}/',
            'Google / Firebase API key' => '/AIza[0-9A-Za-z_\-]{30,}/',
            'Groq key' => '/gsk_[A-Za-z0-9]{20,}/',
            'OpenRouter key' => '/sk-or-[A-Za-z0-9\-]{20,}/',
            'private key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        ] as $what => $pattern) {
            $this->assertDoesNotMatchRegularExpression($pattern, $example, "{$what} in .env.example: move it to .env");
        }

        // Every secret setting is left blank.
        foreach (['APP_KEY', 'MAIL_PASSWORD', 'BREVO_API_KEY', 'FIREBASE_CREDENTIALS_JSON', 'XENDIT_SECRET_KEY', 'XENDIT_WEBHOOK_TOKEN', 'GEMINI_API_KEY', 'GROQ_API_KEY', 'OPENROUTER_API_KEY', 'AWS_SECRET_ACCESS_KEY'] as $name) {
            if (preg_match('/^' . $name . '=(.*)$/m', $example, $value)) {
                $this->assertContains(trim($value[1]), ['', "''", '""', 'null'], "{$name} must be blank in .env.example");
            }
        }
    }
}
