<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseAdminPostRepository;
use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseMessageRepository;
use App\Repositories\FirebaseNotificationHistoryRepository;
use App\Repositories\FirebaseNotificationRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\IotService;
use App\Services\NotificationService;
use App\Services\PriceListService;
use App\Services\PriceUpdateRunner;
use App\Services\PriceUpdateService;
use App\Services\SystemSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class FundWorkflowFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['security.uploads.engines' => ['heuristic']]);
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
    }

    private function as(string $role, string $id = null): static
    {
        return $this->actingAs(new FirebaseUser(['id' => $id ?? "test-{$role}", 'fname' => 'Alex', 'lname' => 'Tester', 'email' => "{$role}@example.test", 'role' => $role]));
    }

    /** No page in these tests may reach Firebase for the topbar bell. */
    private function quietBell(): void
    {
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('getByUser')->andReturn([]));
    }

    public function test_the_price_update_runs_in_the_background_and_only_once_at_a_time(): void
    {
        $runner = new class extends PriceUpdateRunner {
            public array $launched = [];

            protected function launch(array $arguments): void
            {
                $this->launched[] = $arguments;
            }
        };
        $this->app->instance(PriceUpdateRunner::class, $runner);

        $this->as('super_admin')->post(route('superadmin.prices.update'))
            ->assertRedirect(route('superadmin') . '#prices')->assertSessionHas('status');
        $this->assertSame([['prices:update', '--claimed']], $runner->launched, 'the update runs as its own process, not inside the request');

        $this->as('super_admin')->post(route('superadmin.prices.update'))->assertSessionHas('alert_error');
        $this->assertCount(1, $runner->launched, 'a second run never starts while one is going');

        $this->as('super_admin')->getJson(route('superadmin.prices.status'))->assertOk()->assertJson(['running' => true, 'status' => ['state' => 'running']]);
        $this->as('admin')->post(route('superadmin.prices.update'))->assertRedirect(route('admin'));
    }

    public function test_a_failed_price_update_is_recorded_released_and_reported(): void
    {
        $this->mock(PriceUpdateService::class, fn ($mock) => $mock->shouldReceive('run')->andThrow(new \RuntimeException('DTI page unreachable')));
        $this->mock(PriceListService::class, fn ($mock) => $mock->shouldReceive('recordRun')->once()->withArgs(fn ($run) => str_contains($run['failed'], 'DTI page unreachable')));
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('notifyRole')->once()->with('super_admin', 'price_update', 'Price update failed', Mockery::pattern('/DTI page unreachable/'), Mockery::any()));

        $this->artisan('prices:update')->assertExitCode(1);

        $runner = app(PriceUpdateRunner::class);
        $this->assertFalse($runner->isRunning(), 'the lock is released so the next run can start');
        $this->assertSame('failed', $runner->status()['state']);
    }

    public function test_staff_notifications_reach_only_active_accounts_with_that_role(): void
    {
        $users = Mockery::mock(FirebaseUserRepository::class);
        $users->shouldReceive('all')->andReturn([
            ['id' => 'admin-1', 'role' => 'admin'],
            ['id' => 'admin-2', 'role' => 'administrator', 'is_active' => false],
            ['id' => 'boss-1', 'role' => 'supper_admin'],
            ['id' => 'user-1', 'role' => 'normaluser'],
        ]);
        $created = [];
        $store = Mockery::mock(FirebaseNotificationRepository::class);
        $store->shouldReceive('create')->andReturnUsing(function (array $data) use (&$created) {
            $created[] = $data;

            return $data;
        });
        $service = new NotificationService($store, Mockery::mock(FirebaseNotificationHistoryRepository::class), $users);

        $this->assertSame(1, $service->notifyAdmins('req-1', 'new_request', 'New funding request', 'Hope Home asks help.'));
        $this->assertSame(1, $service->notifySuperAdmins('req-1', 'awaiting_final', 'Ready for your decision', 'Admin recommends approval.'));

        $this->assertSame(['admin-1', 'boss-1'], array_column($created, 'user_id'));
        $this->assertSame('/admin/fund-request/req-1', $created[0]['link']);
        $this->assertSame('/superadmin/fund-request/req-1', $created[1]['link']);
    }

    public function test_opening_a_notification_goes_to_what_it_is_about_and_never_off_site(): void
    {
        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldReceive('markAsRead')->with('n-1', 'test-admin')->andReturn(['id' => 'n-1', 'link' => '/admin/fund-request/req-1']);
        $notifications->shouldReceive('markAsRead')->with('n-2', 'test-admin')->andReturn(['id' => 'n-2', 'link' => 'https://evil.example/phish']);

        $this->as('admin')->post(route('notifications.read', 'n-1'))->assertRedirect('/admin/fund-request/req-1');
        $this->as('admin')->post(route('notifications.read', 'n-2'))->assertRedirect(route('notifications'));
    }

    public function test_the_super_admin_sees_the_whole_request_for_the_final_check(): void
    {
        $this->quietBell();
        $request = [
            'id' => 'req-1', 'user_id' => 'user-1', 'org_name' => 'Hope Home', 'category' => 'Food & Nutrition', 'category_name' => 'Food & Nutrition',
            'status' => 'under review', 'status_name' => 'under review', 'purpose' => 'Food packs for 25 elderly residents.',
            'beneficiaries' => ['Ana Cruz', 'Ben Reyes'], 'beneficiary_count' => 25, 'amount_requested' => 12500,
            'contact_person' => 'Maria Santos', 'contact_email' => 'maria@example.test', 'created_at' => now()->subDays(9)->toIso8601String(),
            'documents' => ['request_letter' => 'fund_documents/request_letter/letter.pdf'],
            'assessment' => ['interview_at' => now()->subDays(5)->toIso8601String(), 'social_worker' => 'Liza Ramos', 'assessed_at' => now()->subDays(4)->toIso8601String(), 'outcome' => 'recommended', 'verified_beneficiaries' => 25, 'notes' => 'Visited the home; residents confirmed.'],
            'budget' => ['per_person' => 500, 'people' => 25, 'items' => [['ref' => 'rice', 'name' => 'Rice 5 kg', 'qty' => 1, 'unit_price' => 500, 'subtotal' => 500]]],
            'admin_decision' => 'approved', 'admin_recommended_amount' => 12500, 'ai_recommended_amount' => 11000,
            'admin_notes' => 'Barangay confirmed the list.', 'admin_reviewed_by' => 'admin-1', 'admin_reviewed_at' => now()->subDay()->toIso8601String(),
            'staff_notes' => ['n1' => ['body' => 'Called the barangay captain.', 'author_name' => 'Jo Admin', 'at' => now()->subDay()->toIso8601String()]],
            'disbursement' => ['method' => 'bank_transfer', 'bank_name' => 'Land Bank', 'account_name' => 'Hope Home', 'account_number' => '1234567890'],
        ];
        $this->mock(FirebaseFundingRepository::class, function ($mock) use ($request) {
            $mock->shouldReceive('findById')->with('req-1')->andReturn($request);
            $mock->shouldReceive('all')->andReturn([$request]);
            $mock->shouldReceive('findByUserId')->andReturn([$request]);
        });
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findById')->andReturnUsing(fn ($id) => $id === 'user-1'
            ? ['id' => 'user-1', 'fname' => 'Maria', 'lname' => 'Santos', 'email' => 'maria.requester@example.test', 'phone' => '0917', 'created_at' => '2026-01-05T00:00:00+08:00']
            : ['id' => $id, 'fname' => 'Jo', 'lname' => 'Admin']));
        $this->mock(FirebaseMessageRepository::class, fn ($mock) => $mock->shouldReceive('forRequest')->andReturn([
            ['id' => 'm1', 'side' => 'requester', 'sender_name' => 'Maria Santos', 'body' => 'Is the interview on Monday?', 'created_at' => now()->subDays(6)->toIso8601String()],
        ]));
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getAvailableFunds')->andReturn(50000.0));
        $this->mock(PriceListService::class, fn ($mock) => $mock->shouldReceive('items')->andReturn([]));
        $this->mock(SystemSettings::class, fn ($mock) => $mock->shouldReceive('requestIntervalDays')->andReturn(93));

        $this->as('super_admin')->get(route('superadmin.fund-request.show', 'req-1'))
            ->assertOk()
            ->assertSee('Hope Home')
            ->assertSee('maria.requester@example.test')         // the requester's account
            ->assertSee('Food packs for 25 elderly residents.')  // what they asked for
            ->assertSee('Visited the home; residents confirmed.') // social worker's findings
            ->assertSee('Barangay confirmed the list.')          // admin's note
            ->assertSee('Called the barangay captain.')          // verification note
            ->assertSee('Rice 5 kg')                             // budget
            ->assertSee('Is the interview on Monday?')           // conversation
            ->assertSee('Approve request')->assertSee('Reject request');

        $this->as('admin')->get(route('superadmin.fund-request.show', 'req-1'))->assertRedirect(route('admin'));
    }

    public function test_a_rejected_request_can_be_appealed_at_most_twice(): void
    {
        $rejected = ['id' => 'req-1', 'user_id' => 'user-1', 'status' => 'rejected', 'status_name' => 'rejected', 'appeals' => 1,
            'rejected_at' => '2026-10-01T10:00:00+08:00', 'final_notes' => 'List not verified.', 'documents' => ['request_letter' => 'a.pdf']];
        $repository = Mockery::mock(FirebaseFundingRepository::class);
        $repository->shouldReceive('findById')->with('req-1')->andReturn($rejected, $rejected, ['appeals' => 2] + $rejected);
        $saved = null;
        $repository->shouldReceive('update')->once()->andReturnUsing(function ($id, $data) use (&$saved, $rejected) {
            $saved = $data;

            return array_merge($rejected, $data);
        });
        $approvals = Mockery::mock(FirebaseApprovalRepository::class);
        $approvals->shouldReceive('create')->once();
        $this->app->instance(FirebaseFundingRepository::class, $repository);
        $this->app->instance(FirebaseApprovalRepository::class, $approvals);
        $this->mock(SystemSettings::class);
        $this->mock(NotificationService::class);

        $funding = app(FundingService::class);
        $this->assertNull($funding->appeal('req-1', 'someone-else', 'Updated list', 'b.pdf'), 'only the requester may appeal');

        $updated = $funding->appeal('req-1', 'user-1', 'The barangay signed the list.', 'fund_documents/appeals/list.pdf');
        $this->assertSame('pending', $updated['status']);
        $this->assertSame(2, $saved['appeals']);
        $this->assertSame('fund_documents/appeals/list.pdf', $saved['documents']['appeal_2']);
        $this->assertSame('List not verified.', $saved['appeal_history'][0]['previous_reason'], 'the earlier decision stays in the history');
        $this->assertNull($saved['admin_decision'], 'the admins review it again');

        $this->assertNull($funding->appeal('req-1', 'user-1', 'Third try', 'c.pdf'), 'no third appeal');
    }

    public function test_admins_can_add_a_photo_to_an_update(): void
    {
        Storage::fake('public');
        $posts = $this->mock(FirebaseAdminPostRepository::class);
        $posts->shouldReceive('create')->once()->withArgs(function (array $post) {
            return $post['title'] === 'Rice drive' && str_starts_with((string) $post['image'], 'announcements/') && Storage::disk('public')->exists($post['image']);
        })->andReturnUsing(fn ($post) => $post + ['id' => 'p1']);

        $this->as('admin')->post(route('admin.posts.store'), [
            'title' => 'Rice drive', 'body' => 'We delivered 40 sacks.', 'type' => 'announcement', 'audience' => 'public',
            'image' => UploadedFile::fake()->createWithContent('rice.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')),
        ])->assertRedirect(route('admin') . '#updates')->assertSessionHas('status');
    }

    public function test_two_or_more_updates_play_as_a_looping_slideshow(): void
    {
        $this->mock(FirebaseAdminPostRepository::class, fn ($mock) => $mock->shouldReceive('latest')->andReturn([
            ['id' => 'p1', 'title' => 'Rice drive', 'body' => 'Delivered.', 'type' => 'announcement', 'image' => 'announcements/rice.png', 'created_at' => '2026-10-02T09:00:00+08:00'],
            ['id' => 'p2', 'title' => 'Medical mission', 'body' => 'Next Saturday.', 'type' => 'funding_update', 'created_at' => '2026-10-01T09:00:00+08:00'],
        ]));

        $this->get(route('landing'))->assertOk()
            ->assertSee('data-carousel', false)
            ->assertSee('Rice drive')->assertSee('Medical mission')
            ->assertSee('storage/announcements/rice.png', false)
            ->assertSee('Pause the slideshow');
    }
}
