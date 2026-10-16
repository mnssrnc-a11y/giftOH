<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\NotificationService;
use App\Services\RequestMessageService;
use App\Services\SystemSettings;
use Mockery;
use Tests\TestCase;

/**
 * The requester can cancel a request until the funds go out, and the admin / social worker
 * decides the amount a request needs in the assessment.
 */
class CancelAndAmountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
    }

    private function as(string $role, string $id): static
    {
        return $this->actingAs(new FirebaseUser(['id' => $id, 'fname' => 'Alex', 'lname' => 'Tester', 'role' => $role]));
    }

    /** FundingService on an in-memory request; returns [service, &saved]. */
    private function service(array $request, ?array &$saved): FundingService
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
        $this->mock(NotificationService::class);

        return app(FundingService::class);
    }

    public function test_a_request_can_be_cancelled_until_the_funds_go_out(): void
    {
        $approved = ['id' => 'req-1', 'user_id' => 'user-1', 'status' => 'approved', 'status_name' => 'approved', 'approved_amount' => 12000, 'beneficiaries' => ['Ana Cruz']];
        $funding = $this->service($approved, $saved);

        $this->assertTrue($funding->cancellable($approved), 'approved and waiting for the release');
        $this->assertNull($funding->cancelRequest('req-1', 'someone-else', null), 'only the requester may cancel');

        $updated = $funding->cancelRequest('req-1', 'user-1', 'The residents moved to another home.');
        $this->assertSame('cancelled', $funding->statusOf($updated));
        $this->assertSame('The residents moved to another home.', $saved['cancel_reason']);
        $this->assertSame('Approved · awaiting release', $saved['cancelled_stage']);
        $this->assertSame(['cancelled', 'Cancelled · waiting for the super admin to confirm', 0], array_values($funding->stageOf($updated)));
        $this->assertFalse($funding->cancellable($updated), 'already cancelled');
        // Nothing else can happen on it: no release, no list of people.
        $this->assertNull($funding->recordDisbursement('req-1', ['amount' => 12000], 'admin-1'));
        $this->assertNull($funding->submitBeneficiaries('req-1', [['last' => 'Cruz', 'first' => 'Ana', 'middle' => '']]));

        foreach ([
            'released' => ['disbursement' => ['released_at' => '2026-10-01']],
            'transfer on its way' => ['disbursement' => ['payout' => ['status' => 'processing']]],
            'completed' => ['status' => 'completed', 'status_name' => 'completed'],
            'rejected' => ['status' => 'rejected', 'status_name' => 'rejected'],
        ] as $case => $changes) {
            $this->assertFalse($funding->cancellable(array_merge($approved, $changes)), "not when {$case}");
        }
        foreach (['pending', 'under review'] as $status) {
            $this->assertTrue($funding->cancellable(['status' => $status, 'status_name' => $status]), "while {$status}");
        }
    }

    public function test_a_cancelled_request_blocks_the_next_one_until_the_super_admin_confirms_it(): void
    {
        $cancelled = ['id' => 'req-1', 'user_id' => 'user-1', 'status' => 'cancelled', 'status_name' => 'cancelled', 'created_at' => now()->subDays(3)->toIso8601String()];
        $repository = Mockery::mock(FirebaseFundingRepository::class);
        $repository->shouldReceive('findByUserId')->andReturnUsing(function () use (&$cancelled) { return [$cancelled]; });
        $this->app->instance(FirebaseFundingRepository::class, $repository);
        $this->mock(SystemSettings::class, fn ($mock) => $mock->shouldReceive('requestIntervalDays')->andReturn(93));
        $this->mock(NotificationService::class);

        $this->assertSame('Your cancelled request is waiting for the super admin to confirm the cancellation. You can send a new request once it is confirmed.',
            app(FundingService::class)->requestDenialReason('user-1'));

        // Confirmed: a cancelled request was never granted, so it does not hold up the next one.
        $cancelled['cancel_confirmed_at'] = now()->toIso8601String();
        $this->assertNull(app(FundingService::class)->requestDenialReason('user-1'));
        $this->assertSame('Cancelled · confirmed by the super admin', app(FundingService::class)->stageOf($cancelled)['label']);
    }

    public function test_only_an_unconfirmed_cancellation_can_be_confirmed(): void
    {
        $funding = $this->service(['id' => 'req-1', 'user_id' => 'user-1', 'status' => 'cancelled', 'status_name' => 'cancelled'], $saved);

        $this->assertNotNull($funding->confirmCancellation('req-1', 'boss-1', 'Checked by phone.'));
        $this->assertSame('boss-1', $saved['cancel_confirmed_by']);
        $this->assertSame('Checked by phone.', $saved['cancel_confirm_note']);
        $this->assertNull($funding->confirmCancellation('req-1', 'boss-1', null), 'only once');

        $pending = $this->service(['id' => 'req-2', 'user_id' => 'user-1', 'status' => 'pending', 'status_name' => 'pending'], $saved);
        $this->assertNull($pending->confirmCancellation('req-2', 'boss-1', null), 'not a cancelled request');
    }

    public function test_the_requester_cancels_from_the_request_page_and_staff_are_told(): void
    {
        $request = ['id' => 'req-1', 'user_id' => 'user-1', 'org_name' => 'Hope Home', 'status' => 'pending', 'status_name' => 'pending'];
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getRequestById')->with('req-1')->andReturn($request);
        $funding->shouldReceive('cancelRequest')->once()->with('req-1', 'user-1', 'Found another sponsor.')->andReturn(['status' => 'cancelled'] + $request);
        $funding->shouldReceive('statusOf')->andReturn('pending');
        // Logged, with no amount: nothing was approved yet, so the release line does not change.
        $this->mock(\App\Services\TransactionLog::class, fn ($mock) => $mock->shouldReceive('record')->once()->with('cancelled', $request, null, ['reason' => 'Found another sponsor.']));
        $this->mock(\App\Services\FundQueue::class, fn ($mock) => $mock->shouldNotReceive('refresh'));
        $this->mock(NotificationService::class, function ($mock) {
            // The admins are told it left their list (no link to it); the super admin is asked to confirm it.
            $mock->shouldReceive('notifyRole')->once()->with('admin', 'request_cancelled', 'Request cancelled',
                Mockery::pattern('/Hope Home cancelled their request\. Reason: Found another sponsor\. The process stopped and it was removed from your list/'));
            $mock->shouldNotReceive('notifyAdmins');
            $mock->shouldReceive('notifySuperAdmins')->once()->with('req-1', 'request_cancelled', 'Request cancelled · confirm it', Mockery::pattern('/Confirm the cancellation so the requester can send a new request\./'));
        });
        $this->mock(RequestMessageService::class, fn ($mock) => $mock->shouldReceive('system')->once());

        $this->as('normaluser', 'user-2')->post(route('fund-request.cancel', 'req-1'), ['confirm' => '1'])->assertNotFound();
        $this->as('normaluser', 'user-1')->post(route('fund-request.cancel', 'req-1'), [])->assertSessionHasErrors('confirm');
        $this->as('normaluser', 'user-1')->post(route('fund-request.cancel', 'req-1'), ['confirm' => '1', 'cancel_reason' => ' Found another sponsor. '])
            ->assertRedirect(route('fund-request.show', 'req-1'))->assertSessionHas('success');
    }

    public function test_the_assessment_sets_the_amount_needed_and_approval_waits_for_it(): void
    {
        $pending = ['id' => 'req-1', 'user_id' => 'user-1', 'status' => 'pending', 'status_name' => 'pending', 'beneficiary_count' => 30,
            'assessment' => ['interview_at' => now()->subDay()->toIso8601String(), 'social_worker' => 'Liza']];
        $funding = $this->service($pending, $saved);

        $this->assertNull($funding->amountNeeded($pending));
        $funding->recordAssessment('req-1', ['outcome' => 'recommended', 'verified_beneficiaries' => 28, 'notes' => 'Visited.',
            'amount_needed' => 15250, 'amount_basis' => 'Food packs for two weeks'], 'admin-1');
        $this->assertSame(15250.0, $saved['amount_requested']);
        $this->assertSame(15250.0, $saved['assessment']['amount_needed']);
        $this->assertSame('Food packs for two weeks', $saved['assessment']['amount_basis']);
        $this->assertSame(15250.0, $funding->amountNeeded(array_merge($pending, $saved)));
        // Older requests keep the amount of their per-person budget.
        $this->assertSame(10000.0, $funding->amountNeeded(['budget' => ['per_person' => 500], 'amount_requested' => 10000]));
    }

    public function test_the_admin_must_give_the_amount_when_recommending(): void
    {
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getPendingRequests')->andReturn([]);
        $funding->shouldReceive('getRequestById')->with('req-1')->andReturn(['id' => 'req-1', 'assessment' => ['interview_at' => now()->subDay()->toIso8601String()]]);
        $funding->shouldNotReceive('recordAssessment');

        $this->as('admin', 'admin-1')->post(route('admin.fund-request.assessment', 'req-1'), ['outcome' => 'recommended', 'verified_beneficiaries' => 25, 'notes' => 'Visited.'])
            ->assertSessionHasErrors(['amount_needed' => 'Enter the amount the request needs, based on the assessment.']);
    }
}
