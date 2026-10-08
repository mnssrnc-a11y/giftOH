<?php

namespace Tests\Feature;

use App\Services\FirebaseService;
use Tests\TestCase;

class FirebaseCredentialsTest extends TestCase
{
    /** A made-up service account: the shape matters, not the key. */
    private function serviceAccount(): string
    {
        return json_encode([
            'type' => 'service_account',
            'project_id' => 'demo-project',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASC\n-----END PRIVATE KEY-----\n",
            'client_email' => 'demo@demo-project.iam.gserviceaccount.com',
        ], JSON_PRETTY_PRINT);
    }

    public function test_pasted_credentials_survive_common_dashboard_damage(): void
    {
        $base64 = base64_encode($this->serviceAccount());

        foreach ([
            'base64' => $base64,
            'quoted base64' => "'{$base64}'",
            'base64 broken into lines' => chunk_split($base64, 64, "\n"),
            'raw json' => $this->serviceAccount(),
            'raw json with real line breaks in the key' => str_replace('\n', "\n", $this->serviceAccount()),
        ] as $case => $value) {
            $this->assertSame('demo@demo-project.iam.gserviceaccount.com', FirebaseService::inlineCredentials($value)['client_email'] ?? null, $case);
        }

        $this->assertNull(FirebaseService::inlineCredentials('  '));
    }

    public function test_broken_credentials_say_what_is_wrong_without_showing_the_value(): void
    {
        $cut = substr(base64_encode($this->serviceAccount()), 0, 60);

        try {
            FirebaseService::inlineCredentials($cut);
            $this->fail('A cut-off value must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('cut off', $exception->getMessage());
            $this->assertStringNotContainsString($cut, $exception->getMessage());
        }

        $this->expectExceptionMessage('not a service-account key');
        FirebaseService::inlineCredentials(base64_encode('{"hello": "world"}'));
    }
}
