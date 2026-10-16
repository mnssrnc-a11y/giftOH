<?php

namespace Tests\Feature;

use App\Mail\TransactionVerificationCode;
use App\Models\FirebaseUser;
use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\FundQueue;
use App\Services\IotService;
use App\Services\MailSettingsService;
use App\Services\NotificationService;
use App\Services\Payouts\PayoutService;
use App\Services\TransactionLog;
use App\Services\VerificationService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Release step: an admin sends the amount released through Xendit, confirms with a code emailed
 * to them, and stays on the request page.
 */
class AdminPayoutTest extends TestCase
{
    private array $request = [
        'id' => 'req-1', 'org_name' => 'Hope Home', 'status' => 'approved', 'approved_amount' => 12500,
        'beneficiaries' => ['Ana Cruz'],
        'disbursement' => ['method' => 'e_wallet', 'mode' => 'e_wallet', 'provider' => 'GCash', 'account_name' => 'Hope Home', 'account_number' => '09171234567'],
    ];

    private ?string $code = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payouts.driver' => 'xendit', 'payouts.xendit.secret_key' => 'xnd_test_secret', 'payouts.xendit.webhook_token' => 'hook-token']);
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getBalance')->andReturn(50000.0));
        $this->mock(TransactionLog::class, fn ($mock) => $mock->shouldReceive('record'));
        $this->mock(NotificationService::class);
        $this->mock(\App\Services\SystemSettings::class, fn ($mock) => $mock->shouldReceive('payoutChannels')->andReturn([]));
        $this->mock(FundQueue::class, function ($mock) {
            $mock->shouldReceive('rowFor')->andReturn(['id' => 'req-1', 'position' => 1, 'amount' => 12500.0, 'covered' => true, 'ready' => true, 'short' => 0.0]);
            $mock->shouldReceive('refresh')->andReturn([]);
        });
        $this->mock(FundingService::class, function ($mock) {
            $mock->shouldReceive('getRequestById')->with('req-1')->andReturn($this->request);
            $mock->shouldReceive('stageOf')->andReturn(['key' => 'to_release', 'label' => 'Approved · awaiting release', 'step' => 4]);
        });
        $this->mock(MailSettingsService::class, fn ($mock) => $mock->shouldReceive('canSend')->andReturn(true));
        // The code emailed to the admin, kept like the real one (works once).
        $this->mock(VerificationService::class, function ($mock) {
            $mock->shouldReceive('store')->andReturnUsing(function ($email, $code) { $this->code = $code; });
            $mock->shouldReceive('verify')->andReturnUsing(fn ($email, $code) => $code === $this->code ? ['success' => true, 'record' => []] : ['success' => false, 'error' => 'Invalid code.']);
            $mock->shouldReceive('forget');
        });
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            'api.xendit.co/balance*' => Http::response(['balance' => 1000000]),
            'api.xendit.co/payouts_channels*' => Http::response([['channel_code' => 'PH_GCASH', 'channel_category' => 'EWALLET', 'currency' => 'PHP', 'channel_name' => 'GCash', 'amount_limits' => ['minimum' => 1, 'maximum' => 100000]]]),
            'api.xendit.co/v2/payouts' => Http::response(['id' => 'disb-9', 'status' => 'ACCEPTED']),
        ]);
    }

    private function admin(): static
    {
        return $this->actingAs(new FirebaseUser(['id' => 'admin-1', 'fname' => 'Jo', 'lname' => 'Admin', 'email' => 'jo@example.test', 'role' => 'admin']));
    }

    public function test_an_admin_sends_the_amount_released_and_stays_on_the_release_step(): void
    {
        app(FundingService::class)->shouldReceive('setPayout')->once()->withArgs(fn ($id, $payout) => $payout['amount'] === 10000.0 && $payout['requested_by'] === 'admin-1');
        $page = route('admin.fund-request.show', 'req-1') . '#release';

        $this->admin()->post(route('admin.payout.start', 'req-1'), ['amount' => 10000])
            ->assertRedirect($page)
            ->assertSessionHas('pending_payout', 'req-1')
            ->assertSessionHas('pending_payout_amount', 10000.0);
        Mail::assertSent(TransactionVerificationCode::class, fn ($mail) => $mail->hasTo('jo@example.test'));
        Http::assertNotSent(fn (HttpRequest $sent) => str_contains($sent->url(), '/v2/payouts'));

        $this->admin()->post(route('admin.payout.confirm', 'req-1'), ['code' => $this->code])
            ->assertRedirect($page)
            ->assertSessionHas('status', fn ($message) => str_starts_with($message, '₱10,000.00 sent through Xendit test mode (no real money).'))
            ->assertSessionMissing('pending_payout');
        Http::assertSent(fn (HttpRequest $sent) => $sent->url() === 'https://api.xendit.co/v2/payouts'
            && $sent['amount'] == 10000 && $sent['channel_code'] === 'PH_GCASH'
            && $sent['channel_properties'] === ['account_holder_name' => 'Hope Home', 'account_number' => '09171234567']);
    }

    public function test_the_amount_must_be_chosen_and_not_above_the_approved_amount(): void
    {
        $this->admin()->from(route('admin.fund-request.show', 'req-1'))->post(route('admin.payout.start', 'req-1'))
            ->assertSessionHasErrors(['amount' => 'Choose the amount released with the amount buttons.']);

        $this->admin()->post(route('admin.payout.start', 'req-1'), ['amount' => 13000])
            ->assertSessionHas('alert_error', 'The amount cannot be more than the approved ₱12,500.00.');
        Mail::assertNothingSent();
        Http::assertNotSent(fn (HttpRequest $sent) => str_contains($sent->url(), '/v2/payouts'));
    }

    public function test_a_wrong_code_sends_nothing(): void
    {
        $this->admin()->post(route('admin.payout.start', 'req-1'), ['amount' => 12500]);
        $this->admin()->post(route('admin.payout.confirm', 'req-1'), ['code' => '000000' === $this->code ? '111111' : '000000'])
            ->assertSessionHas('alert_error', 'Invalid code.');
        Http::assertNotSent(fn (HttpRequest $sent) => str_contains($sent->url(), '/v2/payouts'));
    }

    public function test_the_super_admin_still_sends_the_whole_approved_amount(): void
    {
        app(FundingService::class)->shouldReceive('setPayout')->once()->withArgs(fn ($id, $payout) => $payout['amount'] === 12500.0);
        $boss = new FirebaseUser(['id' => 'boss-1', 'fname' => 'Sam', 'email' => 'sam@example.test', 'role' => 'super_admin']);

        $this->actingAs($boss)->post(route('superadmin.payout.start', 'req-1'), ['amount' => 1])
            ->assertRedirect(route('superadmin.fund-request.show', 'req-1') . '#transfer')
            ->assertSessionHas('pending_payout_amount', 12500.0);
        $this->actingAs($boss)->post(route('superadmin.payout.confirm', 'req-1'), ['code' => $this->code])->assertSessionHas('status');
        Http::assertSent(fn (HttpRequest $sent) => str_contains($sent->url(), '/v2/payouts') && $sent['amount'] == 12500);
    }

    public function test_requesters_cannot_send(): void
    {
        $this->actingAs(new FirebaseUser(['id' => 'u1', 'role' => 'normaluser']))
            ->post(route('admin.payout.start', 'req-1'), ['amount' => 100])->assertRedirect();
        Mail::assertNothingSent();
    }
}
