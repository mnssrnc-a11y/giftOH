<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\AdminDashboardService;
use App\Services\AuditLogger;
use App\Services\FundingRequestPresenter;
use App\Services\FundingService;
use App\Services\NotificationService;
use App\Services\RequestMessageService;
use App\Services\SystemSettings;
use Mockery;
use Tests\TestCase;

/**
 * A request the requester cancels stops and leaves the admins' list, stays with the super admin on
 * record, and the requester may ask again once the super admin confirms the cancellation.
 */
class CancellationConfirmTest extends TestCase
{
    private array $requests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requests = [
            'open' => ['id' => 'open', 'user_id' => 'user-1', 'org_name' => 'Bahay Pag-asa', 'status' => 'pending', 'status_name' => 'pending', 'created_at' => now()->subDays(2)->toIso8601String()],
            'gone' => ['id' => 'gone', 'user_id' => 'user-2', 'org_name' => 'Hope Home', 'status' => 'cancelled', 'status_name' => 'cancelled', 'created_at' => now()->subDay()->toIso8601String(),
                'cancelled_at' => now()->subHour()->toIso8601String(), 'cancel_reason' => 'Found another sponsor.', 'cancelled_stage' => 'Interview scheduled'],
        ];
        $repository = Mockery::mock(FirebaseFundingRepository::class);
        $repository->shouldReceive('all')->andReturnUsing(fn () => array_values($this->requests));
        $repository->shouldReceive('findById')->andReturnUsing(fn ($id) => $this->requests[$id] ?? null);
        $repository->shouldReceive('update')->andReturnUsing(function ($id, $data) {
            return $this->requests[$id] = array_merge($this->requests[$id], $data);
        });
        $this->app->instance(FirebaseFundingRepository::class, $repository);
        $this->app->instance(FirebaseApprovalRepository::class, Mockery::mock(FirebaseApprovalRepository::class, fn ($mock) => $mock->shouldReceive('create')));
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findById')->andReturnUsing(fn ($id) => ['id' => $id, 'fname' => 'Sam', 'lname' => 'Boss']));
        $this->mock(SystemSettings::class);
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
        $this->mock(RequestMessageService::class, function ($mock) {
            $mock->shouldReceive('unreadForStaff')->andReturn([]);
            $mock->shouldReceive('system');
        });
    }

    private function as(string $role, string $id): static
    {
        return $this->actingAs(new FirebaseUser(['id' => $id, 'fname' => 'Sam', 'lname' => 'Boss', 'role' => $role]));
    }

    public function test_cancelled_requests_leave_the_admins_list_and_stay_with_the_super_admin(): void
    {
        $dashboard = app(AdminDashboardService::class);

        $this->assertSame(['open'], array_column($dashboard->build(['totalFunds' => null, 'availableFunds' => null, 'smartBoxCount' => 0, 'onlineBoxCount' => 0])['fundingRequests'], 'id'), 'admins: only the open request');
        $rows = collect($dashboard->fundingRows())->keyBy('id');
        $this->assertSame('cancelled', $rows['gone']['status'], 'the super admin keeps it on record');
        $this->assertFalse($rows['gone']['cancel_confirmed']);
    }

    public function test_an_admin_opening_a_cancelled_request_is_sent_back_to_the_list(): void
    {
        $this->as('admin', 'admin-1')->get(route('admin.fund-request.show', 'gone'))
            ->assertRedirect(route('admin') . '#funding')
            ->assertSessionHas('status', 'Hope Home cancelled that request. It is no longer on your list; the super admin keeps its record.');
    }

    public function test_the_super_admin_confirms_the_cancellation_once_and_the_requester_is_told(): void
    {
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('notify')->once()
            ->with('user-2', 'gone', 'cancellation_confirmed', 'Cancellation confirmed', Mockery::pattern('/Hope Home\. You can send a new request\./')));

        $this->as('super_admin', 'boss-1')->post(route('superadmin.fund-request.confirm-cancellation', 'gone'), ['note' => 'Checked by phone.'])
            ->assertRedirect(route('superadmin.fund-request.show', 'gone') . '#cancellation')
            ->assertSessionHas('status');
        $this->assertSame('boss-1', $this->requests['gone']['cancel_confirmed_by']);
        $this->assertSame('Checked by phone.', $this->requests['gone']['cancel_confirm_note']);
        $this->assertSame('cancelled', $this->requests['gone']['status'], 'still on record as cancelled');

        // Only once, and only for cancelled requests.
        $this->as('super_admin', 'boss-1')->post(route('superadmin.fund-request.confirm-cancellation', 'gone'))->assertSessionHas('alert_error');
        $this->as('super_admin', 'boss-1')->post(route('superadmin.fund-request.confirm-cancellation', 'open'))->assertSessionHas('alert_error');
        $this->assertArrayNotHasKey('cancel_confirmed_at', $this->requests['open']);
    }

    public function test_only_the_super_admin_confirms(): void
    {
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldNotReceive('notify'));

        $this->as('admin', 'admin-1')->post(route('superadmin.fund-request.confirm-cancellation', 'gone'))->assertRedirect(route('admin'));
        $this->assertArrayNotHasKey('cancel_confirmed_at', $this->requests['gone']);
    }

    public function test_the_requester_sees_when_it_is_confirmed_but_not_the_staff_note(): void
    {
        $this->mock(NotificationService::class);
        app(FundingService::class)->confirmCancellation('gone', 'boss-1', 'Internal: checked by phone.');
        $presenter = app(FundingRequestPresenter::class);

        $mine = $presenter->present($this->requests['gone'])['cancelled'];
        $this->assertNotNull($mine['confirmed_at']);
        $this->assertNull($mine['confirm_note'], 'the staff note is not shown to the requester');
        $this->assertNull($mine['confirmed_by']);

        $staff = $presenter->present($this->requests['gone'], forAdmin: true);
        $this->assertSame('Internal: checked by phone.', $staff['cancelled']['confirm_note']);
        $this->assertSame('Sam Boss', $staff['cancelled']['confirmed_by']);
        $this->assertContains('Cancellation confirmed by the super admin', array_column($staff['timeline'], 'label'));
    }
}
