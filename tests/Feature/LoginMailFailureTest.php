<?php

namespace Tests\Feature;

use App\Mail\LoginAuthCode;
use App\Repositories\FirebaseUserRepository;
use App\Services\VerificationService;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class LoginMailFailureTest extends TestCase
{
    public function test_smtp_failure_does_not_crash_login_or_store_an_unsent_code(): void
    {
        $email = 'person@example.com';
        $users = Mockery::mock(FirebaseUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->with($email)->andReturn([
            'id' => 'user-1',
            'email' => $email,
            'password' => Hash::make('valid-password'),
            'is_active' => true,
            'email_notifications' => true,
            'fname' => 'Test',
        ]);
        $this->instance(FirebaseUserRepository::class, $users);

        $verification = Mockery::mock(VerificationService::class);
        $verification->shouldNotReceive('store');
        $this->instance(VerificationService::class, $verification);

        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(Mockery::type(LoginAuthCode::class))
            ->andThrow(new TransportException('SMTP authentication failed.'));
        Mail::shouldReceive('to')->once()->with($email)->andReturn($pendingMail);

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'valid-password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('login_2fa_email');
    }
}
