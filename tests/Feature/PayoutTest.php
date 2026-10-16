<?php

namespace Tests\Feature;

use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\FundQueue;
use App\Services\IotService;
use App\Services\NotificationService;
use App\Services\Payouts\PayoutService;
use App\Services\TransactionLog;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    private array $request = [
        'id' => 'req-1', 'org_name' => 'Hope Home', 'status' => 'approved', 'approved_amount' => 12500,
        'beneficiaries' => ['Ana Cruz'],
        'disbursement' => ['method' => 'bank_transfer', 'mode' => 'bank', 'provider' => 'BDO Unibank', 'account_name' => 'Hope Home', 'account_number' => '001234567890'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['payouts.driver' => 'xendit', 'payouts.xendit.secret_key' => 'xnd_test_secret', 'payouts.xendit.webhook_token' => 'hook-token']);
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getBalance')->andReturn(50000.0));
        $this->line(['position' => 1, 'covered' => true, 'ready' => true, 'short' => 0.0]);
        $this->mock(TransactionLog::class, fn ($mock) => $mock->shouldReceive('record'));
        $this->mock(\App\Services\SystemSettings::class, fn ($mock) => $mock->shouldReceive('payoutChannels')->andReturn([]));
        // The payout account holds enough money; BDO takes up to ₱500,000 per transfer. No real calls.
        Http::preventStrayRequests();
        Http::fake([
            'api.xendit.co/balance*' => Http::response(['balance' => 1000000]),
            'api.xendit.co/payouts_channels*' => Http::response([['channel_code' => 'PH_BDO', 'channel_category' => 'BANK', 'currency' => 'PHP', 'channel_name' => 'BDO Unibank', 'amount_limits' => ['minimum' => 1, 'maximum' => 500000]]]),
        ]);
    }

    /** The request's place in the release line (first come, first served). */
    private function line(array $row): Mockery\MockInterface
    {
        return $this->mock(FundQueue::class, function ($mock) use ($row) {
            $mock->shouldReceive('rowFor')->andReturn($row + ['id' => 'req-1', 'amount' => 12500.0]);
            $mock->shouldReceive('refresh')->andReturn([]);
        });
    }

    private function funding(array $request, string $stage = 'to_release'): Mockery\MockInterface
    {
        return $this->mock(FundingService::class, function ($mock) use ($request, $stage) {
            $mock->shouldReceive('getRequestById')->with('req-1')->andReturn($request);
            $mock->shouldReceive('stageOf')->andReturn(['key' => $stage, 'label' => $stage, 'step' => 4]);
        });
    }

    public function test_a_transfer_is_offered_only_when_everything_is_ready(): void
    {
        $this->mock(NotificationService::class);
        $this->funding($this->request, 'awaiting_recipients');
        $check = app(PayoutService::class)->readiness($this->request);
        $this->assertFalse($check['ready']);
        $this->assertStringContainsString('has not listed the people', $check['problems'][0]);

        config(['payouts.driver' => 'manual']);
        $this->funding($this->request);
        $this->assertStringContainsString('not connected yet', app(PayoutService::class)->readiness($this->request)['problems'][0]);

        config(['payouts.driver' => 'xendit']);
        $unsupported = array_replace_recursive($this->request, ['disbursement' => ['provider' => 'PayPal']]);
        $this->assertStringContainsString('PayPal is not set up', app(PayoutService::class)->readiness($unsupported)['problems'][0]);

        $this->assertTrue(app(PayoutService::class)->readiness($this->request)['ready']);

        // First come, first served: not before the donations cover the requests approved earlier.
        $this->line(['position' => 2, 'covered' => false, 'ready' => false, 'short' => 4000.0]);
        $check = app(PayoutService::class)->readiness($this->request);
        $this->assertFalse($check['ready']);
        $this->assertSame(['Not enough donations yet: this request is #2 in line and ₱4,000.00 more is needed.'], $check['problems']);
    }

    public function test_a_bank_or_e_wallet_spelled_differently_still_finds_its_channel(): void
    {
        $this->mock(NotificationService::class);
        $payouts = app(PayoutService::class);

        foreach (['GCash', 'Gcash', 'GCASH', ' g-cash '] as $spelling) {
            $this->assertSame('PH_GCASH', $payouts->channelFor($spelling), $spelling);
        }
        $this->assertSame('PH_BDO', $payouts->channelFor('bdo unibank'));
        $this->assertNull($payouts->channelFor('PayPal'), 'not a Xendit channel');
        $this->assertNull($payouts->channelFor(''));
        $this->assertNull($payouts->channelFor(null));

        // An older request saved as "Gcash" can be sent.
        $this->funding($gcash = array_replace_recursive($this->request, ['disbursement' => ['provider' => 'Gcash', 'mode' => 'e_wallet']]));
        Http::fake(['api.xendit.co/payouts_channels*' => Http::response([['channel_code' => 'PH_GCASH', 'channel_category' => 'EWALLET', 'currency' => 'PHP', 'channel_name' => 'GCash', 'amount_limits' => ['minimum' => 1, 'maximum' => 100000]]])]);
        $this->assertSame('PH_GCASH', $payouts->readiness($gcash)['channel']);
    }

    public function test_sending_pays_the_receiver_account_once_through_xendit(): void
    {
        Http::fake(['api.xendit.co/v2/payouts' => Http::response(['id' => 'disb-77', 'status' => 'ACCEPTED'])]);
        $this->mock(NotificationService::class);
        $funding = $this->funding($this->request);
        $funding->shouldReceive('setPayout')->once()->withArgs(fn ($id, $payout) => $id === 'req-1' && $payout['status'] === 'processing' && $payout['payout_id'] === 'disb-77' && $payout['amount'] === 12500.0);

        app(PayoutService::class)->send('req-1', 'boss-1');

        $this->assertCount(1, Http::recorded(fn (HttpRequest $sent) => str_contains($sent->url(), '/v2/payouts')), 'paid once');
        Http::assertSent(fn (HttpRequest $sent) => $sent->url() === 'https://api.xendit.co/v2/payouts'
            && $sent->hasHeader('Authorization', 'Basic ' . base64_encode('xnd_test_secret:'))
            && $sent->hasHeader('Idempotency-key', 'goh-req-1-1')
            && $sent['reference_id'] === 'goh-req-1'
            && $sent['channel_code'] === 'PH_BDO'
            && $sent['channel_properties'] === ['account_holder_name' => 'Hope Home', 'account_number' => '001234567890']
            && $sent['amount'] == 12500
            && $sent['currency'] === 'PHP');
    }

    public function test_the_webhook_records_the_release_when_the_bank_confirms(): void
    {
        $processing = array_replace_recursive($this->request, ['disbursement' => ['payout' => ['status' => 'processing', 'payout_id' => 'disb-77', 'amount' => 12500.0]]]);
        $funding = $this->funding($processing);
        $funding->shouldReceive('setPayout')->once()->withArgs(fn ($id, $payout) => $payout['status'] === 'succeeded');
        $funding->shouldReceive('recordDisbursement')->once()->withArgs(fn ($id, $release, $by) => $id === 'req-1' && $release['amount'] === 12500.0 && $release['reference_no'] === 'disb-77' && $by === 'system');
        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('getByUser')->andReturn([]);
            $mock->shouldReceive('notifySuperAdmins')->once();
            $mock->shouldReceive('notifyAdmins')->once();
        });
        // The transfer is logged, and the next request in line may now be covered.
        $this->mock(TransactionLog::class, fn ($mock) => $mock->shouldReceive('record')->once()->withArgs(fn ($type, $request, $amount) => $type === 'transferred' && $amount === 12500.0));
        $this->mock(FundQueue::class, function ($mock) {
            $mock->shouldReceive('rowFor')->andReturn(['id' => 'req-1', 'position' => 1, 'covered' => true, 'ready' => true, 'short' => 0.0, 'amount' => 12500.0]);
            $mock->shouldReceive('refresh')->once()->andReturn([]);
        });

        $payload = ['id' => 'disb-77', 'reference_id' => 'goh-req-1', 'status' => 'SUCCEEDED', 'amount' => 12500];
        $this->postJson(route('webhooks.payouts'), $payload, ['x-callback-token' => 'wrong'])->assertStatus(401);
        $this->postJson(route('webhooks.payouts'), $payload, ['x-callback-token' => 'hook-token'])->assertOk()->assertJson(['handled' => true]);
    }
}
