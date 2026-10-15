<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseDeviceAccountRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebasePasswordResetRepository;
use App\Repositories\FirebaseUserRepository;
use App\Repositories\FirebaseVerificationRepository;
use App\Services\AuditLogger;
use App\Services\BoxAccountService;
use App\Services\FirebaseService;
use App\Services\FundingService;
use App\Services\IotService;
use App\Services\MailSettingsService;
use App\Services\NotificationService;
use App\Services\SystemSettings;
use App\Services\TransactionLog;
use App\Services\VerificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Mockery;
use Tests\TestCase;

/**
 * The flaws found in the system test: guessing passwords and codes, decisions when email is down,
 * an assessment without an interview, appeals that kept the old assessment, an unsigned
 * transaction log, and donation box totals anyone could change.
 */
class SecurityFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
    }

    public function test_sign_in_and_code_pages_are_rate_limited(): void
    {
        foreach (['login.store', 'register.store', 'login.verify-code', 'register.verify-code', 'password.send-code', 'password.verify-code', 'password.update', 'admin.fund-request.verify'] as $name) {
            $throttles = array_filter(Route::getRoutes()->getByName($name)->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
            $this->assertNotEmpty($throttles, "{$name} is rate-limited");
        }
    }

    public function test_five_wrong_passwords_lock_the_sign_in_for_a_while(): void
    {
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findByEmail')->andReturn(['id' => 'u1', 'email' => 'ana@example.test', 'password' => Hash::make('Right!234'), 'is_active' => true]));

        foreach (range(1, 5) as $try) {
            $this->post(route('login.store'), ['email' => 'ana@example.test', 'password' => "wrong-{$try}"])
                ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        }
        // Even the right password waits now.
        $this->post(route('login.store'), ['email' => 'ana@example.test', 'password' => 'Right!234'])
            ->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many wrong passwords.', session('errors')->first('email'));
    }

    public function test_a_code_is_thrown_away_after_five_wrong_tries(): void
    {
        $codes = Mockery::mock(FirebaseVerificationRepository::class);
        $codes->shouldReceive('find')->andReturn(['token' => Hash::make('123456'), 'created_at' => now()->toIso8601String()]);
        $codes->shouldReceive('forget')->once();
        $service = new VerificationService($codes, Mockery::mock(FirebasePasswordResetRepository::class));

        foreach (range(1, 4) as $try) {
            $this->assertSame('Invalid code. Please try again.', $service->verify('ana@example.test', '00000' . $try, 'login_auth_codes')['error']);
        }
        $this->assertSame('Too many wrong codes. Please request a new code.', $service->verify('ana@example.test', '000005', 'login_auth_codes')['error']);
    }

    public function test_admin_decisions_go_through_when_email_is_not_set_up(): void
    {
        $request = ['id' => 'req-1', 'org_name' => 'Hope Home', 'assessment' => ['assessed_at' => now()->toIso8601String(), 'outcome' => 'recommended']];
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getRequestById')->andReturn($request);
        $funding->shouldReceive('statusOf')->andReturn('pending');
        $funding->shouldReceive('documentsVerified')->andReturn(true);
        $funding->shouldReceive('amountNeeded')->andReturn(12000.0);
        $funding->shouldReceive('getPendingRequests')->andReturn([]);
        $funding->shouldReceive('submitAdminReview')->once()->andReturn(['status' => 'under review'] + $request);
        $this->mock(MailSettingsService::class, fn ($mock) => $mock->shouldReceive('canSend')->andReturn(false));
        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('notifySuperAdmins')->andReturn(1);
            $mock->shouldReceive('notify')->andReturn([]);
            $mock->shouldReceive('getByUser')->andReturn([]);
        });
        Mail::fake();

        // Codes are on (never set), but no code could be emailed: the decision is sent anyway.
        $this->actingAs(new FirebaseUser(['id' => 'admin-1', 'fname' => 'Jo', 'email' => 'jo@example.test', 'role' => 'admin']))
            ->post(route('admin.fund-request.action', 'req-1'), ['action' => 'approved', 'amount' => 12000])
            ->assertRedirect(route('admin') . '#funding');
        Mail::assertNothingSent();
    }

    /** FundingService on one in-memory request; returns the service, $saved holds what was written. */
    private function funding(array $request, ?array &$saved): FundingService
    {
        $saved = null;
        $repository = Mockery::mock(FirebaseFundingRepository::class);
        $repository->shouldReceive('findById')->andReturnUsing(function () use (&$saved, $request) {
            return $saved ? array_merge($request, $saved) : $request;
        });
        $repository->shouldReceive('update')->andReturnUsing(function ($id, $data) use (&$saved, $request) {
            $saved = array_merge($saved ?? [], $data);

            return array_merge($request, $saved);
        });
        $approvals = Mockery::mock(FirebaseApprovalRepository::class);
        $approvals->shouldReceive('create');
        $this->app->instance(FirebaseFundingRepository::class, $repository);
        $this->app->instance(FirebaseApprovalRepository::class, $approvals);
        $this->mock(SystemSettings::class);
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('notify')->andReturn([]));

        return app(FundingService::class);
    }

    public function test_the_assessment_waits_for_the_interview(): void
    {
        $funding = $this->funding(['id' => 'req-1', 'status' => 'pending', 'status_name' => 'pending'], $saved);
        $result = ['outcome' => 'recommended', 'verified_beneficiaries' => 20, 'notes' => 'x', 'amount_needed' => 9000];

        $this->assertNull($funding->recordAssessment('req-1', $result, 'admin-1'), 'no interview scheduled yet');
        $funding->scheduleInterview('req-1', ['interview_at' => now()->addDay()->toIso8601String(), 'social_worker' => 'Liza', 'mode' => 'phone', 'mode_label' => 'Phone or video call'], 'admin-1');
        $this->assertNotNull($funding->recordAssessment('req-1', $result, 'admin-1'));
    }

    public function test_an_appeal_is_assessed_again_and_keeps_the_old_assessment_in_its_history(): void
    {
        $funding = $this->funding(['id' => 'req-1', 'user_id' => 'u1', 'status' => 'rejected', 'status_name' => 'rejected', 'appeals' => 0,
            'amount_requested' => 9000, 'rejected_at' => '2026-10-01T10:00:00+08:00', 'final_notes' => 'Not eligible.',
            'assessment' => ['interview_at' => '2026-09-28T10:00:00+08:00', 'social_worker' => 'Liza', 'mode' => 'phone',
                'outcome' => 'not_recommended', 'verified_beneficiaries' => 5, 'notes' => 'Few people.', 'amount_needed' => 9000, 'assessed_at' => '2026-09-29T10:00:00+08:00']], $saved);

        $funding->appeal('req-1', 'u1', 'Now 25 people are listed.', 'fund_documents/appeals/list.pdf');

        $this->assertSame(['interview_at' => '2026-09-28T10:00:00+08:00', 'social_worker' => 'Liza', 'mode' => 'phone'], $saved['assessment'], 'the interview stays, the findings go');
        $this->assertSame(0, $saved['amount_requested']);
        $this->assertSame('not_recommended', $saved['appeal_history'][0]['previous_assessment']['outcome']);
        $this->assertSame(['interview_scheduled', 'Interview scheduled', 1], array_values($funding->stageOf(array_merge(['status' => 'pending'], $saved))));
    }

    public function test_the_transaction_log_is_signed_with_the_app_key(): void
    {
        $entry = ['seq' => 1, 'type' => 'approved', 'amount' => 12000.0, 'request_id' => 'req-1', 'prev_hash' => str_repeat('0', 64)];
        $signed = TransactionLog::hash($entry);

        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->assertNotSame($signed, TransactionLog::hash($entry), 'without the app key the hash cannot be recomputed');
    }

    public function test_each_donation_box_gets_its_own_sign_in(): void
    {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('getUserByEmail')->with('box-box-1@boxes.giftofhope.org')->andThrow(new UserNotFound('none'));
        $auth->shouldReceive('createUser')->once()->withArgs(fn ($props) => $props['email'] === 'box-box-1@boxes.giftofhope.org' && strlen($props['password']) === 24)
            ->andReturn(\Kreait\Firebase\Auth\UserRecord::fromResponseData(['localId' => 'uid-1', 'email' => 'box-box-1@boxes.giftofhope.org', 'createdAt' => '1700000000000']));
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('getAuth')->andReturn($auth);
        $accounts = Mockery::mock(FirebaseDeviceAccountRepository::class);
        $accounts->shouldReceive('link')->once()->with('uid-1', 'box-1');

        $issued = (new BoxAccountService($firebase, $accounts))->issue('box-1');
        $this->assertSame(['box-1', 'box-box-1@boxes.giftofhope.org', 'uid-1'], [$issued['box'], $issued['email'], $issued['uid']]);

        $this->expectException(\InvalidArgumentException::class);
        (new BoxAccountService($firebase, $accounts))->issue('../users');
    }

    public function test_the_super_admin_creates_box_sign_ins_for_known_boxes_only(): void
    {
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getBoxes')->andReturn([['id' => 'box-1', 'total' => 100]]));
        $this->mock(BoxAccountService::class, fn ($mock) => $mock->shouldReceive('issue')->once()->with('box-1')
            ->andReturn(['box' => 'box-1', 'email' => 'box-box-1@boxes.giftofhope.org', 'password' => 'Secret123', 'uid' => 'uid-1']));
        $boss = new FirebaseUser(['id' => 'boss-1', 'role' => 'super_admin']);

        $this->actingAs($boss)->post(route('superadmin.boxes.sign-in', 'box-1'))
            ->assertRedirect(route('superadmin') . '#monitor')
            ->assertSessionHas('box_credentials', ['box' => 'box-1', 'email' => 'box-box-1@boxes.giftofhope.org', 'password' => 'Secret123']);
        $this->actingAs($boss)->post(route('superadmin.boxes.sign-in', 'box-9'))->assertNotFound();
    }

    public function test_the_database_rules_let_a_box_write_only_its_own_growing_total(): void
    {
        $rules = json_decode(file_get_contents(base_path('firebase.database.rules.json')), true)['rules'];

        $this->assertSame("auth != null && newData.exists() && root.child('device_accounts').child(auth.uid).val() === \$boxId", $rules['boxes']['$boxId']['.write']);
        $this->assertStringContainsString('newData.val() >= data.val()', $rules['boxes']['$boxId']['total']['.validate']);
        $this->assertStringStartsWith('auth != null', $rules['logs']['$boxId']['.write']);
        $this->assertFalse($rules['device_accounts']['.write']);
    }
}
