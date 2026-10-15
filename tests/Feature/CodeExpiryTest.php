<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebasePasswordResetRepository;
use App\Repositories\FirebaseVerificationRepository;
use App\Services\Payouts\PayoutService;
use App\Services\VerificationService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Emailed codes (sign-in, registration, password reset, approval, transfer) work for 5 minutes
 * only, and once.
 */
class CodeExpiryTest extends TestCase
{
    private function record(int $minutesAgo, string $code = '123456'): array
    {
        return ['email' => 'ana@example.test', 'token' => Hash::make($code), 'created_at' => now()->subMinutes($minutesAgo)->toIso8601String()];
    }

    public function test_a_code_is_expired_after_its_minutes(): void
    {
        $this->assertFalse(VerificationService::isExpired(now()->toIso8601String(), 5));
        $this->assertFalse(VerificationService::isExpired(now()->subMinutes(4)->toIso8601String(), 5));
        $this->assertTrue(VerificationService::isExpired(now()->subMinutes(6)->toIso8601String(), 5));
        $this->assertTrue(VerificationService::isExpired(now()->subDays(3)->toIso8601String(), 5), 'an old code never works again');
        $this->assertTrue(VerificationService::isExpired(null, 5), 'no time: expired');
        $this->assertTrue(VerificationService::isExpired('not a date', 5));
    }

    public function test_codes_are_refused_after_five_minutes_and_accepted_before(): void
    {
        $codes = Mockery::mock(FirebaseVerificationRepository::class);
        $codes->shouldReceive('find')->with('login', 'ana@example.test')->andReturn($this->record(30), $this->record(2));
        $codes->shouldReceive('forget')->once()->with('login', 'ana@example.test');
        $service = new VerificationService($codes, Mockery::mock(FirebasePasswordResetRepository::class));

        $this->assertSame(['success' => false, 'error' => 'This code has expired. Please request a new one.'],
            $service->verify('ana@example.test', '123456', 'login_auth_codes'), 'a 30-minute-old code');
        $this->assertTrue($service->verify('ana@example.test', '123456', 'login_auth_codes')['success'], 'a 2-minute-old code');
    }

    public function test_password_reset_codes_expire_too(): void
    {
        $resets = Mockery::mock(FirebasePasswordResetRepository::class);
        $resets->shouldReceive('find')->andReturn($this->record(10));
        $resets->shouldReceive('forget')->once();
        $service = new VerificationService(Mockery::mock(FirebaseVerificationRepository::class), $resets);

        $this->assertFalse($service->verify('ana@example.test', '123456', 'password_reset_tokens')['success']);
    }

    public function test_the_repository_check_expires_codes(): void
    {
        $repository = Mockery::mock(FirebaseVerificationRepository::class)->makePartial();
        $repository->shouldReceive('find')->andReturn($this->record(20), $this->record(1));
        $repository->shouldReceive('forget')->once();

        $this->assertFalse($repository->verify('login', 'ana@example.test', '123456'));
        $this->assertTrue($repository->verify('login', 'ana@example.test', '123456'));
    }

    public function test_the_reset_session_expires_after_five_minutes(): void
    {
        $this->mock(VerificationService::class, function ($mock) {
            $mock->shouldReceive('find')->andReturn($this->record(30, 'reset-token'));
            $mock->shouldReceive('forget')->once();
        });

        $this->post(route('password.update'), ['email' => 'ana@example.test', 'token' => 'reset-token', 'password' => 'NewSecret!1', 'password_confirmation' => 'NewSecret!1'])
            ->assertSessionHasErrors(['email' => 'Reset session expired. Please start over.']);
    }

    public function test_a_transfer_code_works_once(): void
    {
        $boss = new FirebaseUser(['id' => 'boss-1', 'fname' => 'Boss', 'email' => 'boss@example.test', 'role' => 'super_admin']);
        $this->mock(VerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(['success' => true, 'record' => []]);
            $mock->shouldReceive('forget')->once()->with('boss@example.test', 'transaction_verification_codes');
        });
        $this->mock(PayoutService::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn(['amount' => 1000.0, 'mode' => 'test']);
            $mock->shouldReceive('modeLabel')->andReturn('Xendit test mode (no real money)');
        });

        $this->actingAs($boss)->withSession(['pending_payout' => 'req-1'])
            ->post(route('superadmin.payout.confirm', 'req-1'), ['code' => '123456'])
            ->assertSessionHas('status');
    }
}
