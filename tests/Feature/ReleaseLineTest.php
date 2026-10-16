<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseDonationRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseIotBoxRepository;
use App\Repositories\FirebaseMessageRepository;
use App\Repositories\FirebaseTransactionLogRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\AuditLogger;
use App\Services\FirebaseService;
use App\Services\FundingService;
use App\Services\FundQueue;
use App\Services\IotService;
use App\Services\NotificationService;
use App\Services\SystemSettings;
use App\Services\TransactionLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Approved requests get the donations first come, first served; the funds on hand go down when
 * money is transferred; every money step is logged and the log cannot be changed unnoticed.
 */
class ReleaseLineTest extends TestCase
{
    private array $requests = [];

    private ?float $collected = 30000.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
    }

    private function approved(string $id, float $amount, string $approvedAt, array $extra = []): array
    {
        return array_merge(['id' => $id, 'user_id' => "user-{$id}", 'org_name' => "Home {$id}", 'status' => 'approved', 'status_name' => 'approved',
            'approved_amount' => $amount, 'approved_at' => $approvedAt, 'beneficiaries' => ['Ana Cruz'],
            'disbursement' => ['method' => 'e_wallet', 'provider' => 'GCash', 'account_name' => "Home {$id}", 'account_number' => '09171234567']], $extra);
    }

    /** FundQueue over in-memory requests; the donations collected are $this->collected. */
    private function queue(?NotificationService $notifications = null, ?TransactionLog $ledger = null): FundQueue
    {
        $repository = Mockery::mock(FirebaseFundingRepository::class);
        $repository->shouldReceive('all')->andReturnUsing(fn () => array_values($this->requests));
        $repository->shouldReceive('findById')->andReturnUsing(fn ($id) => $this->requests[$id] ?? null);
        $repository->shouldReceive('update')->andReturnUsing(function ($id, $data) {
            $this->requests[$id] = array_replace_recursive($this->requests[$id], $data);

            return $this->requests[$id];
        });
        $this->app->instance(FirebaseFundingRepository::class, $repository);
        $this->app->instance(FirebaseApprovalRepository::class, Mockery::mock(FirebaseApprovalRepository::class));
        $this->mock(SystemSettings::class);
        $notifications ??= Mockery::mock(NotificationService::class);
        $this->app->instance(NotificationService::class, $notifications);
        $funding = app(FundingService::class);

        $iot = Mockery::mock(IotService::class, [Mockery::mock(FirebaseIotBoxRepository::class), Mockery::mock(FirebaseDonationRepository::class), $funding])->makePartial();
        $iot->shouldReceive('getTotalFunds')->andReturnUsing(fn () => $this->collected);

        return new FundQueue($funding, $iot, $notifications, $ledger ?? Mockery::mock(TransactionLog::class, ['record' => null]));
    }

    public function test_donations_go_to_approved_requests_first_come_first_served(): void
    {
        $this->requests = [
            'paid' => $this->approved('paid', 10000, '2026-09-01T09:00:00+08:00', ['disbursement' => ['released_at' => '2026-09-05', 'amount' => 10000]]),
            'c' => $this->approved('c', 3000, '2026-10-03T09:00:00+08:00'),
            'a' => $this->approved('a', 12000, '2026-10-01T09:00:00+08:00'),
            'b' => $this->approved('b', 10000, '2026-10-02T09:00:00+08:00'),
        ];
        $state = $this->queue()->state();

        $this->assertSame(20000.0, $state['balance'], '₱30,000 collected minus the ₱10,000 already transferred');
        $this->assertSame(['a', 'b', 'c'], array_column($state['rows'], 'id'), 'first approved first');
        $this->assertSame([true, false, false], array_column($state['rows'], 'covered'));
        $this->assertSame(2000.0, $state['rows'][1]['short'], 'b needs ₱2,000 more after a');
        $this->assertSame(5000.0, $state['rows'][2]['short'], 'c waits its turn even though ₱3,000 would fit');
        $this->assertSame(12000.0, $state['set_aside']);
        $this->assertSame(0.0, $state['free']);

        // What each requester is shown: set aside so far, and how much of the gap is the line ahead.
        $this->assertSame([12000.0, 8000.0, 0.0], array_column($state['rows'], 'raised'));
        $this->assertSame([0.0, 0.0, 2000.0], array_column($state['rows'], 'ahead_short'), 'b still needs ₱2,000 for c to start');
        $this->assertSame([true, true, true], array_column($state['rows'], 'known'));
    }

    public function test_an_unreadable_donation_total_is_unknown_not_zero(): void
    {
        $this->collected = null;
        $this->requests = ['a' => $this->approved('a', 5000, '2026-10-01T09:00:00+08:00')];
        $row = $this->queue()->rowFor('a');

        $this->assertFalse($row['covered']);
        $this->assertFalse($row['known']);
        $this->assertNull($row['raised']);
    }

    /** The requester pages over the in-memory line; signed in as the owner of $id. */
    private function asRequesterOf(string $id): void
    {
        $queue = $this->queue();
        app(FirebaseFundingRepository::class)->shouldReceive('findByUserId')->andReturnUsing(
            fn ($userId) => array_values(array_filter($this->requests, fn ($r) => $r['user_id'] === $userId)));
        app(NotificationService::class)->shouldReceive('getByUser')->andReturn([]);
        $this->app->instance(FundQueue::class, $queue);
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findById')->andReturn(null));
        $this->mock(\App\Services\RequestMessageService::class, fn ($mock) => $mock->shouldReceive('thread')->andReturn([])->getMock()->shouldReceive('markRead'));
        $this->actingAs(new FirebaseUser(['id' => "user-{$id}", 'fname' => 'Maria', 'lname' => 'Santos', 'role' => 'normaluser']));
    }

    public function test_the_requester_sees_how_much_is_missing_before_the_transfer(): void
    {
        $this->requests = [
            'a' => $this->approved('a', 12000, '2026-10-01T09:00:00+08:00'),
            'b' => $this->approved('b', 10000, '2026-10-02T09:00:00+08:00'),
            'c' => $this->approved('c', 3000, '2026-10-03T09:00:00+08:00'),
        ];
        $this->collected = 20000.0;
        $this->asRequesterOf('c');

        $this->get(route('fund-request.show', 'c'))->assertOk()
            ->assertSee('₱5,000.00 more in donations is needed')
            ->assertSee('You are #3 in line.')
            ->assertSee('₱0.00 of ₱3,000.00 set aside for you · ₱3,000.00 to go')
            ->assertSee('This includes ₱2,000.00 still needed by requests approved before yours');

        $this->get(route('request-status'))->assertOk()
            ->assertSee('₱5,000.00 more in donations needed before transfer');
    }

    public function test_a_covered_request_shows_no_missing_amount(): void
    {
        $this->requests = ['a' => $this->approved('a', 12000, '2026-10-01T09:00:00+08:00')];
        $this->asRequesterOf('a');

        $this->get(route('fund-request.show', 'a'))->assertOk()
            ->assertSee('Your funds are ready.')
            ->assertDontSee('more in donations is needed');
        $this->get(route('request-status'))->assertOk()->assertDontSee('more in donations needed');
    }

    public function test_admins_are_told_once_when_a_request_is_ready_and_the_next_follows_after_the_transfer(): void
    {
        $this->collected = 20000.0;
        $this->requests = [
            'a' => $this->approved('a', 12000, '2026-10-01T09:00:00+08:00'),
            'b' => $this->approved('b', 10000, '2026-10-02T09:00:00+08:00'),
        ];
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifyAdmins')->once()->with('a', 'funds_ready', 'Funds ready · transfer now', Mockery::pattern('/₱12,000\.00 for Home a .*#1 in line.*GCash · Home a/'));
        $ledger = Mockery::mock(TransactionLog::class);
        $ledger->shouldReceive('record')->once()->with('set_aside', Mockery::on(fn ($r) => $r['id'] === 'a'), 12000.0, Mockery::type('array'), 'System');
        $queue = $this->queue($notifications, $ledger);

        $this->assertSame(['a'], $queue->refresh());
        $this->assertSame([], $queue->refresh(), 'announced once');
        $this->assertTrue($queue->isReady('a'));
        $this->assertFalse($queue->isReady('b'));

        // The transfer to "a": the funds on hand go down by what was sent; b is still short.
        $this->requests['a']['disbursement'] += ['released_at' => now()->toDateString(), 'amount' => 12000];
        $this->assertSame(8000.0, $queue->state()['balance']);
        $this->assertSame(['b'], array_column($queue->state()['rows'], 'id'));

        // New donations arrive: b is covered and announced.
        $this->collected = 40000.0;
        $notifications->shouldReceive('notifyAdmins')->once()->with('b', 'funds_ready', Mockery::any(), Mockery::any());
        $ledger->shouldReceive('record')->once()->with('set_aside', Mockery::any(), 10000.0, Mockery::any(), 'System');
        $this->assertSame(['b'], $queue->refresh());
    }

    public function test_a_covered_request_waits_for_its_list_of_people_before_the_admins_are_told(): void
    {
        $this->requests = ['a' => $this->approved('a', 5000, '2026-10-01T09:00:00+08:00', ['beneficiaries' => []])];
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldNotReceive('notifyAdmins');
        $queue = $this->queue($notifications);

        $row = $queue->rowFor('a');
        $this->assertTrue($row['covered']);
        $this->assertFalse($row['ready']);
        $this->assertSame([], $queue->refresh());
    }

    public function test_no_transfer_is_recorded_before_the_donations_cover_the_request(): void
    {
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getRequestById')->with('b')->andReturn($this->approved('b', 10000, '2026-10-02T09:00:00+08:00'));
        $funding->shouldNotReceive('recordDisbursement');
        $this->mock(FundQueue::class, fn ($mock) => $mock->shouldReceive('rowFor')->with('b')->andReturn(['id' => 'b', 'position' => 2, 'covered' => false, 'ready' => false, 'short' => 2000.0]));
        $this->actingAs(new FirebaseUser(['id' => 'admin-1', 'fname' => 'Jo', 'lname' => 'Admin', 'role' => 'admin']));

        $this->post(route('admin.fund-request.disbursement', 'b'), ['method' => 'e_wallet', 'amount' => 10000])
            ->assertRedirect(route('admin.fund-request.show', 'b'))
            ->assertSessionHas('alert_error', 'Not enough donations yet: this request is #2 in line and ₱2,000.00 more is needed before it can be transferred.');
    }

    /** A release form for request "a" (approved ₱12,000, covered), with the amount chosen by the buttons. */
    private function release(?float $amount, bool $records): \Illuminate\Testing\TestResponse
    {
        Storage::fake(config('funding.files_disk'));
        $request = $this->approved('a', 12000, '2026-10-01T09:00:00+08:00');
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getRequestById')->with('a')->andReturn($request);
        $records
            ? $funding->shouldReceive('recordDisbursement')->once()->with('a', Mockery::on(fn ($data) => $data['amount'] === $amount), 'admin-1')->andReturn($request)
            : $funding->shouldNotReceive('recordDisbursement');
        $this->mock(FundQueue::class, function ($mock) {
            $mock->shouldReceive('rowFor')->with('a')->andReturn(['id' => 'a', 'position' => 1, 'covered' => true, 'ready' => true, 'short' => 0.0]);
            $mock->shouldReceive('refresh');
        });
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getBalance')->andReturn(18000.0));
        $this->mock(TransactionLog::class, fn ($mock) => $records
            ? $mock->shouldReceive('record')->once()->with('transferred', $request, $amount, Mockery::type('array'))
            : $mock->shouldNotReceive('record'));
        $this->actingAs(new FirebaseUser(['id' => 'admin-1', 'fname' => 'Jo', 'lname' => 'Admin', 'role' => 'admin']));

        return $this->post(route('admin.fund-request.disbursement', 'a'), array_filter([
            'method' => 'e_wallet', 'amount' => $amount, 'released_at' => now()->toDateString(), 'reference_no' => 'GC-123',
            'proof' => UploadedFile::fake()->createWithContent('slip.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF"),
        ], fn ($value) => $value !== null));
    }

    public function test_the_amount_chosen_with_the_buttons_is_released(): void
    {
        $this->release(10500.0, true)->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_no_manual_release_while_a_xendit_transfer_is_on_its_way(): void
    {
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getRequestById')->with('a')->andReturn($this->approved('a', 12000, '2026-10-01T09:00:00+08:00', [
            'disbursement' => ['method' => 'e_wallet', 'provider' => 'GCash', 'account_name' => 'Home a', 'account_number' => '09171234567', 'payout' => ['status' => 'processing']],
        ]));
        $funding->shouldNotReceive('recordDisbursement');
        $this->mock(FundQueue::class, fn ($mock) => $mock->shouldReceive('rowFor')->andReturn(['id' => 'a', 'position' => 1, 'covered' => true, 'ready' => true, 'short' => 0.0]));
        $this->actingAs(new FirebaseUser(['id' => 'admin-1', 'fname' => 'Jo', 'lname' => 'Admin', 'role' => 'admin']));

        $this->post(route('admin.fund-request.disbursement', 'a'), ['method' => 'e_wallet', 'amount' => 12000, 'released_at' => now()->toDateString(), 'reference_no' => 'GC-1'])
            ->assertRedirect(route('admin.fund-request.show', 'a') . '#release')
            ->assertSessionHas('alert_error', fn ($message) => str_starts_with($message, 'A transfer through Xendit is on its way'));
    }

    public function test_the_release_cannot_be_more_than_approved_or_empty(): void
    {
        $this->release(12100.0, false)->assertSessionHasErrors(['amount' => 'The released amount cannot exceed the approved amount.']);
        $this->release(null, false)->assertSessionHasErrors(['amount' => 'Choose the amount released with the amount buttons.']);
    }

    public function test_the_transaction_log_is_chained_and_cannot_be_changed_unnoticed(): void
    {
        $stored = [];
        $repository = Mockery::mock(FirebaseTransactionLogRepository::class);
        $repository->shouldReceive('inOrder')->andReturnUsing(function () use (&$stored) {
            return array_values($stored);
        });
        $repository->shouldReceive('create')->andReturnUsing(function ($entry) use (&$stored) {
            // Firebase drops empty values and returns whole numbers as integers.
            $entry = json_decode(json_encode(array_filter($entry, fn ($v) => $v !== null && $v !== [])), true);
            $stored[$entry['id']] = $entry;

            return $entry;
        });
        $ledger = new TransactionLog($repository);
        $request = ['id' => 'req-1', 'org_name' => 'Hope Home'];

        $ledger->record('approved', $request, 12000.0, ['note' => null], 'Super Admin');
        $ledger->record('transferred', $request, 12000.0, ['reference_no' => 'BDO-551', 'funds_on_hand_after' => 8000.0], 'Jo Admin');
        $ledger->record('cancelled', ['id' => 'req-2', 'org_name' => 'Other Home'], null, [], 'System');

        $this->assertSame(['ok' => true, 'count' => 3, 'broken' => null], $ledger->verify());
        $this->assertSame($stored['tx-0000000001']['hash'], $stored['tx-0000000002']['prev_hash']);
        $this->assertSame(['transferred', 'approved'], array_column($ledger->latest('req-1'), 'type'), 'newest first, one request');

        // Someone edits an amount directly in the database: the log shows where.
        $stored['tx-0000000002']['amount'] = 1200;
        $this->assertSame(['ok' => false, 'count' => 1, 'broken' => 2], $ledger->verify());
        $stored['tx-0000000002']['amount'] = 12000;
        unset($stored['tx-0000000001']);
        $this->assertSame(['ok' => false, 'count' => 0, 'broken' => 1], $ledger->verify());

        // The app itself never edits or deletes an entry.
        $real = new FirebaseTransactionLogRepository(Mockery::mock(FirebaseService::class));
        $this->expectException(\LogicException::class);
        $real->update('tx-0000000001', ['amount' => 1]);
    }

    public function test_the_request_page_opens_on_the_current_step_with_the_admin_as_social_worker(): void
    {
        $request = ['id' => 'req-1', 'user_id' => 'user-1', 'org_name' => 'Hope Home', 'status' => 'pending', 'status_name' => 'pending',
            'category' => 'Food', 'beneficiary_count' => 25, 'created_at' => now()->subDay()->toIso8601String()];
        $this->mock(FirebaseFundingRepository::class, function ($mock) use ($request) {
            $mock->shouldReceive('findById')->andReturn($request);
            $mock->shouldReceive('all')->andReturn([$request]);
        });
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findById')->andReturn(null));
        $this->mock(FirebaseMessageRepository::class, fn ($mock) => $mock->shouldReceive('forRequest')->andReturn([]));
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getAvailableFunds')->andReturn(5000.0));
        $this->mock(FundQueue::class, function ($mock) {
            $mock->shouldReceive('refreshSoon');
            $mock->shouldReceive('rowFor')->andReturn(null);
        });
        $this->mock(TransactionLog::class, fn ($mock) => $mock->shouldReceive('latest')->andReturn([]));
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('getByUser')->andReturn([]));
        $this->mock(SystemSettings::class);

        $response = $this->actingAs(new FirebaseUser(['id' => 'admin-1', 'fname' => 'Liza', 'lname' => 'Ramos', 'role' => 'admin']))
            ->get(route('admin.fund-request.show', 'req-1'))
            ->assertOk()
            ->assertSee('id="social_worker" name="social_worker" placeholder="e.g. Liza Ramos, RSW" required maxlength="120" value="Liza Ramos"', false)
            // No interview yet: the assessment step asks for it first instead of showing its form.
            ->assertDontSee('id="assessment_sw"', false)
            ->assertSee('Schedule the interview first: the assessment records what the social worker found in it.')
            ->assertSee('No money has moved on this request yet.');

        // Step by step: no interview yet, so the interview step is open and the others wait.
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-tab-panel="interview"\s*>/', $html);
        foreach (['documents', 'assessment', 'decision', 'release', 'liquidation'] as $step) {
            $this->assertMatchesRegularExpression('/data-tab-panel="' . $step . '"\s+hidden/', $html, "{$step} is hidden");
        }
        $this->assertMatchesRegularExpression('/data-tab="release"\s+aria-disabled="true"/', $html, 'release opens after approval');
    }
}
