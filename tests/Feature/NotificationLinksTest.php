<?php

namespace Tests\Feature;

use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\FundingRequestPresenter;
use App\Services\FundingService;
use App\Services\NotificationService;
use App\Services\SystemSettings;
use Mockery;
use Tests\TestCase;

/**
 * Opening a notification lands on the part of the request it is about, and the requester's
 * progress reaches "admin review" once the interview is confirmed and the admin is verifying.
 */
class NotificationLinksTest extends TestCase
{
    public function test_a_notification_opens_the_section_it_is_about(): void
    {
        $this->assertSame('/fund-request/req-1#documents', NotificationService::destinationFor(
            ['type' => 'document_resubmit', 'request_id' => 'req-1', 'link' => '/fund-request/req-1'], 'user'));
        $this->assertSame('/admin/fund-request/req-1#messages', NotificationService::destinationFor(
            ['type' => 'message', 'request_id' => 'req-1', 'link' => '/admin/fund-request/req-1'], 'admin'));
        $this->assertSame('/superadmin/fund-request/req-1#decision', NotificationService::destinationFor(
            ['type' => 'awaiting_final', 'request_id' => 'req-1', 'link' => '/superadmin/fund-request/req-1'], 'super_admin'));
    }

    public function test_without_a_link_the_role_request_page_is_used(): void
    {
        $this->assertSame('/admin/fund-request/req-2#interview', NotificationService::destinationFor(
            ['type' => 'new_request', 'request_id' => 'req-2'], 'admin'));
        $this->assertSame('/fund-request/req-2#release', NotificationService::destinationFor(
            ['type' => 'funding_approved', 'request_id' => 'req-2'], 'user'));
    }

    public function test_existing_anchors_unknown_types_and_unsafe_links(): void
    {
        // A link that already names a section is kept as it is.
        $this->assertSame('/superadmin#settings', NotificationService::destinationFor(
            ['type' => 'message', 'link' => '/superadmin#settings'], 'super_admin'));
        // A type without a section opens the page itself.
        $this->assertSame('/fund-request/req-3', NotificationService::destinationFor(
            ['type' => 'something_new', 'request_id' => 'req-3', 'link' => '/fund-request/req-3'], 'user'));
        // Off-site links are never followed; the request page is used instead.
        $this->assertSame('/fund-request/req-3#messages', NotificationService::destinationFor(
            ['type' => 'message', 'request_id' => 'req-3', 'link' => 'https://evil.example/x'], 'user'));
        $this->assertNull(NotificationService::destinationFor(['type' => 'message', 'link' => '//evil.example'], 'user'));
    }

    private function presenter(): FundingRequestPresenter
    {
        $this->app->instance(FirebaseFundingRepository::class, Mockery::mock(FirebaseFundingRepository::class));
        $this->app->instance(FirebaseApprovalRepository::class, Mockery::mock(FirebaseApprovalRepository::class));
        $this->app->instance(FirebaseUserRepository::class, Mockery::mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findById')->andReturn(null)));
        $this->mock(SystemSettings::class);
        $this->mock(NotificationService::class);

        return app(FundingRequestPresenter::class);
    }

    private function request(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'req-1',
            'org_name' => 'Hope Home',
            'status' => 'pending',
            'category_name' => 'Food',
            'created_at' => now()->toIso8601String(),
            'documents' => ['formal_letter' => 'a.pdf', 'indigency_cert' => 'b.pdf'],
            'assessment' => ['interview_at' => now()->addDay()->toIso8601String()],
        ], $overrides);
    }

    public function test_progress_reaches_admin_review_once_confirmed_and_verifying(): void
    {
        $view = $this->presenter()->present($this->request([
            'assessment' => ['interview_response' => ['status' => 'confirmed']],
            'document_reviews' => ['formal_letter' => ['status' => 'verified']],
        ]));

        $this->assertSame(['step' => 2, 'label' => 'Interview confirmed · under admin review'], $view['progress']);
        // The admin's own stage is unchanged.
        $this->assertSame(1, $view['stage']['step']);
    }

    public function test_progress_follows_the_stage_until_the_admin_starts_verifying(): void
    {
        $presenter = $this->presenter();

        // Confirmed, but no document verified yet.
        $view = $presenter->present($this->request(['assessment' => ['interview_response' => ['status' => 'confirmed']]]));
        $this->assertSame(['step' => 1, 'label' => 'Interview scheduled'], $view['progress']);

        // Documents verified, but the interview not confirmed.
        $view = $presenter->present($this->request(['document_reviews' => ['formal_letter' => ['status' => 'verified']]]));
        $this->assertSame(1, $view['progress']['step']);

        // Later stages are not held back.
        $view = $presenter->present($this->request(['status' => FundingService::STATUS_UNDER_REVIEW]));
        $this->assertSame(['step' => 3, 'label' => 'Awaiting final approval'], $view['progress']);
    }
}
