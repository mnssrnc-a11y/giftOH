<?php

namespace Tests\Feature;

use App\Repositories\FirebaseUserRepository;
use App\Services\AuditLogger;
use App\Services\EnvironmentFile;
use App\Services\MailSettingsService;
use App\Services\NotificationService;
use App\Services\RegistrationService;
use App\Services\SystemSettings;
use Mockery;
use Tests\TestCase;

class RegistrationWithoutMailTest extends TestCase
{
    public function test_can_send_needs_the_password_or_key_of_the_chosen_mailer(): void
    {
        $settings = Mockery::mock(SystemSettings::class);
        $settings->shouldReceive('brevoApiKey')->andReturn(null);
        $mail = new MailSettingsService(new EnvironmentFile(), $settings);

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.username' => 'sender@gmail.com', 'mail.mailers.smtp.password' => '']);
        $this->assertFalse($mail->canSend(), 'SMTP login without a password cannot send');

        config(['mail.mailers.smtp.password' => 'app-password']);
        $this->assertTrue($mail->canSend());

        config(['mail.mailers.smtp.username' => null, 'mail.mailers.smtp.password' => null, 'mail.mailers.smtp.host' => 'localhost']);
        $this->assertTrue($mail->canSend(), 'an unauthenticated relay (e.g. Mailpit) needs no password');

        config(['mail.default' => 'brevo', 'mail.mailers.brevo.key' => '']);
        $this->assertFalse($mail->canSend(), 'Brevo without a saved or configured key cannot send');

        config(['mail.mailers.brevo.key' => 'test-key']);
        $this->assertTrue($mail->canSend());

        config(['mail.default' => 'log']);
        $this->assertTrue($mail->canSend());
    }

    public function test_the_key_saved_by_the_super_admin_wins_over_the_env_key(): void
    {
        $settings = Mockery::mock(SystemSettings::class);
        $settings->shouldReceive('brevoApiKey')->andReturn('saved-key');
        $settings->shouldReceive('mailSender')->andReturn(null);
        config(['mail.default' => 'brevo', 'mail.mailers.brevo.key' => 'env-key']);

        $mail = new MailSettingsService(new EnvironmentFile(), $settings);

        $this->assertSame('saved-key', $mail->brevoKey());
        $this->assertTrue($mail->canSend());
        $this->assertSame('…-key', $mail->current()['key_hint']);
    }

    public function test_an_account_created_without_an_emailed_code_signs_in_without_codes(): void
    {
        $saved = null;
        $users = Mockery::mock(FirebaseUserRepository::class);
        $users->shouldReceive('create')->once()->andReturnUsing(function (array $data) use (&$saved) {
            $saved = $data;

            return $data + ['id' => 'new-user'];
        });
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once();

        $user = (new RegistrationService($users, $audit, $this->notifications()))->createAccount($this->pending(), emailConfirmed: false);

        $this->assertSame('normaluser', $saved['role']);
        $this->assertFalse($saved['email_notifications'], 'sign-in codes must start off, or the person is locked out');
        $this->assertFalse($saved['email_confirmed']);
        $this->assertSame('dashboarduser', $user->homeRoute());
    }

    public function test_an_account_confirmed_by_code_keeps_sign_in_codes_on(): void
    {
        $saved = null;
        $users = Mockery::mock(FirebaseUserRepository::class);
        $users->shouldReceive('create')->once()->andReturnUsing(function (array $data) use (&$saved) {
            $saved = $data;

            return $data + ['id' => 'new-user'];
        });
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldNotReceive('record');

        (new RegistrationService($users, $audit, $this->notifications()))->createAccount($this->pending(), emailConfirmed: true);

        $this->assertTrue($saved['email_notifications']);
        $this->assertTrue($saved['email_confirmed']);
    }

    /** The super admins are told about every new account. */
    private function notifications(): NotificationService
    {
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifyRole')->once()
            ->with('super_admin', 'new_account', Mockery::any(), Mockery::any(), Mockery::any())->andReturn(1);

        return $notifications;
    }

    private function pending(): array
    {
        return [
            'fname' => 'Test', 'lname' => 'Person', 'mname' => null,
            'email' => 'test.person@example.com', 'password' => 'hashed',
            'phone' => '09170000000', 'address' => 'Street, Barangay, City, Province',
            'gender' => null, 'date_of_birth' => null, 'profile_picture' => null,
        ];
    }
}
