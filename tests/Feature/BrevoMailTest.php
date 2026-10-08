<?php

namespace Tests\Feature;

use App\Mail\LoginAuthCode;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Tests\TestCase;

/**
 * MAIL_MAILER=brevo sends through Brevo's HTTPS API (Render's free plan blocks SMTP ports).
 */
class BrevoMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'test-key',
            'mail.from.address' => 'giftofhope@example.com',
            'mail.from.name' => 'Gift of Hope',
        ]);
    }

    public function test_verification_codes_are_sent_through_the_brevo_api(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201)]);

        Mail::to('donor@example.com')->send(new LoginAuthCode('123456', 'Ana'));

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-key')
                && $body['sender']['email'] === 'giftofhope@example.com'
                && $body['to'][0]['email'] === 'donor@example.com'
                && str_contains($body['htmlContent'] ?? '', '123456');
        });
    }

    public function test_a_rejected_email_raises_the_usual_mail_error(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Sender not valid'], 400)]);

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Sender not valid');

        Mail::to('donor@example.com')->send(new LoginAuthCode('123456', 'Ana'));
    }
}
