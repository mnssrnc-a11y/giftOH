<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\FundQueue;
use App\Services\IotService;
use App\Services\NotificationService;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGateway;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\SandboxPayoutGateway;
use App\Services\Payouts\XenditPayoutGateway;
use App\Services\SystemSettings;
use App\Services\TransactionLog;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * The bank-to-bank API: the Xendit Payouts API (send, status, balance, channel list), the built-in
 * practice bank (sandbox), checking transfers without a webhook, and the payout channel settings.
 */
class BankTransferApiTest extends TestCase
{
    private array $transfer = [
        'reference_id' => 'goh-req-1', 'idempotency_key' => 'goh-req-1-1', 'channel_code' => 'SBX_GCASH',
        'account_number' => '0917 123 4567', 'account_name' => 'Hope Home', 'amount' => 12500.0, 'description' => 'Gift of Hope grant',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
    }

    public function test_the_sandbox_bank_accepts_then_confirms_without_moving_real_money(): void
    {
        config(['payouts.driver' => 'sandbox', 'payouts.sandbox.balance' => 20000, 'payouts.sandbox.settle_seconds' => 30]);
        $bank = new SandboxPayoutGateway();

        $sent = $bank->send($this->transfer);
        $this->assertSame('ACCEPTED', $sent['status']);
        $this->assertStringStartsWith('sbx_', $sent['id']);
        $this->assertSame($sent['id'], $bank->send($this->transfer)['id'], 'same idempotency key: never paid twice');
        $this->assertSame('ACCEPTED', $bank->status($sent['id'])['status']);
        $this->assertSame(20000.0, $bank->balance());

        $this->travel(31)->seconds();
        $this->assertSame('SUCCEEDED', $bank->status($sent['id'])['status']);
        $bank->status($sent['id']);
        $this->assertSame(7500.0, $bank->balance(), 'taken from the practice account once');

        // Xendit's failing test accounts fail the same way; too much money is refused.
        $failing = $bank->send(['account_number' => '999999', 'idempotency_key' => 'k2', 'amount' => 100.0] + $this->transfer);
        $this->travel(31)->seconds();
        $this->assertSame(['status' => 'FAILED', 'failure_code' => 'REJECTED_BY_CHANNEL'], array_intersect_key($bank->status($failing['id']), ['status' => 1, 'failure_code' => 1]));
        $this->expectException(PayoutException::class);
        $bank->send(['idempotency_key' => 'k3', 'amount' => 50000.0] + $this->transfer);
    }

    public function test_the_sandbox_lists_every_bank_and_e_wallet_of_the_request_form(): void
    {
        $names = array_column((new SandboxPayoutGateway())->channels(), 'name');
        foreach (['BDO Unibank', 'Land Bank of the Philippines', 'Metrobank', 'GCash', 'Maya'] as $provider) {
            $this->assertContains($provider, $names);
        }
        $this->assertSame('sandbox', (new SandboxPayoutGateway())->mode());
    }

    public function test_the_xendit_api_status_balance_and_channel_list(): void
    {
        config(['payouts.xendit.secret_key' => 'xnd_development_abc', 'payouts.xendit.base_url' => 'https://api.xendit.co']);
        Http::fake([
            'api.xendit.co/v2/payouts/disb-1' => Http::response(['id' => 'disb-1', 'status' => 'FAILED', 'failure_code' => 'INVALID_DESTINATION']),
            'api.xendit.co/balance*' => Http::response(['balance' => 250000.5]),
            'api.xendit.co/payouts_channels*' => Http::response([
                ['channel_code' => 'PH_LBP', 'channel_category' => 'BANK', 'currency' => 'PHP', 'channel_name' => 'Land Bank of the Philippines', 'amount_limits' => ['minimum' => 1, 'maximum' => 50000, 'minimum_increment' => 0.01]],
                ['channel_code' => 'ID_BCA', 'channel_category' => 'BANK', 'currency' => 'IDR', 'channel_name' => 'Bank Central Asia'],
                ['channel_code' => 'PH_GCASH', 'channel_category' => 'EWALLET', 'currency' => 'PHP', 'channel_name' => 'GCash'],
            ]),
        ]);
        $xendit = new XenditPayoutGateway();

        $this->assertSame(['id' => 'disb-1', 'status' => 'FAILED', 'failure_code' => 'INVALID_DESTINATION'], $xendit->status('disb-1'));
        $this->assertSame(250000.5, $xendit->balance());
        $this->assertSame([
            ['code' => 'PH_GCASH', 'name' => 'GCash', 'category' => 'EWALLET', 'minimum' => null, 'maximum' => null],
            ['code' => 'PH_LBP', 'name' => 'Land Bank of the Philippines', 'category' => 'BANK', 'minimum' => 1.0, 'maximum' => 50000.0],
        ], $xendit->channels(), 'Philippine channels only, by name');
        $this->assertSame('test', $xendit->mode());
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'balance?account_type=CASH&currency=PHP') && $r->hasHeader('Authorization', 'Basic ' . base64_encode('xnd_development_abc:')));

        config(['payouts.xendit.secret_key' => 'xnd_production_abc']);
        $this->assertSame('live', $xendit->mode());
    }

    public function test_a_transfer_in_progress_is_recorded_from_a_status_check_without_the_webhook(): void
    {
        config(['payouts.driver' => 'sandbox']);
        $request = ['id' => 'req-1', 'org_name' => 'Hope Home', 'status' => 'approved', 'approved_amount' => 12500, 'beneficiaries' => ['Ana'],
            'disbursement' => ['method' => 'e_wallet', 'provider' => 'GCash', 'account_name' => 'Hope Home', 'account_number' => '09171234567',
                'payout' => ['status' => 'processing', 'payout_id' => 'sbx_1', 'amount' => 12500.0, 'mode' => 'sandbox']]];
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getRequestById')->with('req-1')->andReturn($request, ['disbursement' => ['payout' => ['status' => 'succeeded']]] + $request);
        $funding->shouldReceive('setPayout')->once()->withArgs(fn ($id, $payout) => $payout['status'] === 'succeeded');
        $funding->shouldReceive('recordDisbursement')->once()->withArgs(fn ($id, $release, $by) => $release['reference_no'] === 'sbx_1'
            && str_contains($release['notes'], 'no real money') && $by === 'system');
        $this->mock(PayoutGateway::class, fn ($mock) => $mock->shouldReceive('status')->with('sbx_1')->andReturn(['id' => 'sbx_1', 'status' => 'SUCCEEDED', 'failure_code' => null]));
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('notifySuperAdmins', 'notifyAdmins')->once());
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getBalance')->andReturn(7500.0));
        $this->mock(TransactionLog::class, fn ($mock) => $mock->shouldReceive('record')->once()
            ->withArgs(fn ($type, $r, $amount, $details) => $type === 'transferred' && $details['mode'] === 'Sandbox (no real money)'));
        $this->mock(FundQueue::class, fn ($mock) => $mock->shouldReceive('refresh')->once());
        $this->mock(SystemSettings::class);

        $this->assertSame('succeeded', app(PayoutService::class)->sync('req-1'));
    }

    public function test_the_payout_channel_comes_from_system_settings_and_the_account_must_hold_the_money(): void
    {
        config(['payouts.driver' => 'xendit', 'payouts.xendit.secret_key' => 'xnd_development_abc']);
        $this->mock(SystemSettings::class, fn ($mock) => $mock->shouldReceive('payoutChannels')->andReturn(['Land Bank of the Philippines' => 'PH_LBP']));
        $service = app(PayoutService::class);
        $this->assertSame('PH_LBP', $service->channelFor('Land Bank of the Philippines'), 'chosen by the super admin');
        $this->assertSame('PH_BDO', $service->channelFor('BDO Unibank'), 'confirmed codes stay');
        $this->assertNull($service->channelFor('PayPal'));

        Http::fake([
            'api.xendit.co/balance*' => Http::response(['balance' => 3000]),
            'api.xendit.co/payouts_channels*' => Http::response([['channel_code' => 'PH_LBP', 'currency' => 'PHP', 'channel_name' => 'Land Bank', 'amount_limits' => ['minimum' => 1, 'maximum' => 200000000]],
                ['channel_code' => 'PH_GCASH', 'currency' => 'PHP', 'channel_name' => 'GCash', 'amount_limits' => ['minimum' => 1, 'maximum' => 100000]]]),
        ]);
        $funding = $this->mock(FundingService::class, fn ($mock) => $mock->shouldReceive('stageOf')->andReturn(['key' => 'to_release', 'label' => '', 'step' => 4]));
        $this->mock(FundQueue::class, fn ($mock) => $mock->shouldReceive('rowFor')->andReturn(['position' => 1, 'covered' => true, 'ready' => true, 'short' => 0.0]));
        $check = app(PayoutService::class)->readiness(['id' => 'req-1', 'approved_amount' => 12500,
            'disbursement' => ['provider' => 'Land Bank of the Philippines', 'account_name' => 'Hope Home', 'account_number' => '123456789']]);
        $this->assertFalse($check['ready']);
        $this->assertSame(['The payout account has ₱3,000.00. Add money to it to send ₱12,500.00.'], $check['problems']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'payouts_channels'));

        // GCash takes at most ₱100,000 per transfer.
        $big = app(PayoutService::class)->readiness(['id' => 'req-2', 'approved_amount' => 150000,
            'disbursement' => ['provider' => 'GCash', 'account_name' => 'Hope Home', 'account_number' => '09171234567']]);
        $this->assertSame(['GCash takes at most ₱100,000.00 per transfer. Send this grant by hand and record the release.'], $big['problems']);
    }

    public function test_the_super_admin_saves_payout_channels_for_listed_banks_only(): void
    {
        $settings = $this->mock(SystemSettings::class);
        $settings->shouldReceive('setPayoutChannels')->once()->with(['Land Bank of the Philippines' => 'PH_LBP', 'Maya' => 'PH_PAYMAYA']);
        $boss = new FirebaseUser(['id' => 'boss-1', 'fname' => 'Boss', 'role' => 'super_admin']);

        $this->actingAs($boss)->post(route('superadmin.settings.payouts'), ['channels' => [
            'Land Bank of the Philippines' => 'PH_LBP', 'Maya' => 'PH_PAYMAYA', 'Not a bank' => 'PH_X', 'Metrobank' => '',
        ]])->assertRedirect(route('superadmin') . '#settings-payouts')->assertSessionHas('status');
        $this->actingAs($boss)->post(route('superadmin.settings.payouts'), ['channels' => ['Metrobank' => 'ph metro;drop']])->assertSessionHasErrors('channels.Metrobank');
        $this->actingAs(new FirebaseUser(['id' => 'a-1', 'role' => 'admin']))->post(route('superadmin.settings.payouts'), [])->assertRedirect(route('admin'));
    }
}
