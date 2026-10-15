<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseAdminPostRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\FundingService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HelpTourTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('getByUser')->andReturn([]));
    }

    private function as(string $role, array $extra = []): static
    {
        return $this->actingAs(new FirebaseUser($extra + ['id' => "test-{$role}", 'fname' => 'Alex', 'lname' => 'Tester', 'role' => $role]));
    }

    private function tutorialsOn($response): array
    {
        return json_decode(str($response->getContent())->between('window.giftOfHopeTour = ', ';</script>'), true);
    }

    public function test_every_tutorial_is_short_and_points_at_a_real_page(): void
    {
        $tutorials = config('tour.tutorials');
        $this->assertNotEmpty($tutorials);

        foreach ($tutorials as $id => $tutorial) {
            $this->assertTrue(Route::has($tutorial['route']), "{$id}: unknown route {$tutorial['route']}");
            $this->assertEmpty(array_diff($tutorial['roles'], ['user', 'admin', 'super_admin']), "{$id}: unknown role");
            $this->assertNotEmpty($tutorial['about'], "{$id}: Help needs a line about it");
            // At most 4 steps show at a time: the ones always there, plus those of one stage ("when").
            $byStage = collect($tutorial['steps'])->groupBy(fn (array $step) => $step['when'] ?? '');
            $shown = count($byStage->get('', [])) + $byStage->except('')->map->count()->max();
            $this->assertLessThanOrEqual(4, $shown, "{$id}: keep it short");
            foreach ($tutorial['steps'] as $step) {
                $this->assertLessThanOrEqual(110, mb_strlen($step['text']), "{$id}: \"{$step['text']}\" is too long");
                $this->assertContains($step['do'] ?? null, [null, 'click', 'fill']);
                if (isset($step['next'])) {
                    $this->assertArrayHasKey($step['next'], $tutorials, "{$id}: next tutorial {$step['next']} is missing");
                }
            }
        }

        // Every role gets a tutorial for each of its workspace sections.
        $sections = fn (string $route) => collect($tutorials)->where('route', $route)->pluck('section')->sort()->values()->all();
        $this->assertSame(['dashboard', 'funding', 'reports', 'settings', 'updates'], $sections('admin'));
        $this->assertSame(['accounts', 'activity', 'dashboard', 'monitor', 'requests', 'settings', 'transactions'], $sections('superadmin'));
    }

    public static function requestStages(): array
    {
        return [['pending'], ['approved'], ['released']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('requestStages')]
    public function test_the_request_page_tutorial_points_at_what_the_page_shows_at_each_stage(string $stage): void
    {
        $base = ['id' => 'req-1', 'user_id' => 'test-normaluser', 'org_name' => 'Hope Home', 'category' => 'Food', 'beneficiary_count' => 2,
            'created_at' => now()->subDays(10)->toIso8601String(), 'documents' => ['formal_letter' => 'a.pdf', 'indigency_cert' => 'b.pdf'],
            'disbursement' => ['method' => 'e_wallet', 'mode' => 'e_wallet', 'provider' => 'GCash', 'account_name' => 'Hope Home', 'account_number' => '09171234567']];
        $request = match ($stage) {
            'pending' => $base + ['status' => 'pending', 'assessment' => ['interview_at' => now()->addDay()->toIso8601String(), 'mode_label' => 'Home visit'],
                'document_reviews' => ['formal_letter' => ['status' => 'resubmit', 'remarks' => 'Blurry']]],
            'approved' => $base + ['status' => 'approved', 'approved_amount' => 1000, 'approved_at' => now()->subDay()->toIso8601String(), 'beneficiaries' => []],
            'released' => array_replace($base, ['status' => 'approved', 'approved_amount' => 1000, 'beneficiaries' => ['Ana Cruz'],
                'disbursement' => $base['disbursement'] + ['released_at' => now()->subDay()->toIso8601String(), 'amount' => 1000]]),
        };
        $this->mock(\App\Repositories\FirebaseFundingRepository::class, fn ($mock) => $mock->shouldReceive('findById')->with('req-1')->andReturn($request));
        $this->mock(\App\Repositories\FirebaseMessageRepository::class, fn ($mock) => $mock->shouldReceive('forRequest')->andReturn([]));
        $this->mock(\App\Services\SystemSettings::class);
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findById')->andReturn(null));
        $this->mock(\App\Services\FundQueue::class, fn ($mock) => $mock->shouldReceive('rowFor')->andReturn(['position' => 1, 'amount' => 1000.0, 'covered' => true,
            'ready' => false, 'short' => 0.0, 'raised' => 1000.0, 'ahead_short' => 0.0, 'known' => true]));

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $this->as('normaluser')->get(route('fund-request.show', 'req-1'))->assertOk()->getContent());
        $xpath = new \DOMXPath($dom);
        $converter = new \Symfony\Component\CssSelector\CssSelectorConverter();
        foreach (config('tour.tutorials.request-detail.steps') as $step) {
            $css = preg_match('/[.#[\s:>]/', $step['target']) ? $step['target'] : "[data-tour=\"{$step['target']}\"]";
            // The steps of this stage (and those always there) are on the page; other stages' are not.
            $this->assertSame(($step['when'] ?? $stage) === $stage, $xpath->query($converter->toXPath($css))->length > 0, "{$stage}: \"{$step['title']}\" ({$step['target']})");
        }
    }

    public function test_help_lists_the_tutorials_of_each_role(): void
    {
        $this->mock(FundingService::class, function ($mock) {
            $mock->shouldReceive('getRequestsByUser')->andReturn([['id' => 'old', 'created_at' => '2026-01-01'], ['id' => 'new', 'created_at' => '2026-05-01']]);
            $mock->shouldReceive('getAllRequests')->andReturn([]);
        });

        $this->get(route('help'))->assertRedirect(route('login'));

        $this->as('normaluser', ['tutorials_seen' => ['welcome']])->get(route('help'))->assertOk()
            ->assertSee('Tutorials')
            ->assertSee(e(route('fund-request', ['tutorial' => 'fund-request'])), false)
            // A request page tutorial opens on the newest request.
            ->assertSee(e(route('fund-request.show', ['id' => 'new', 'tutorial' => 'request-detail'])), false)
            ->assertSee('✓ Done')
            ->assertDontSee('Announcements with photos.');

        $this->as('admin')->get(route('help'))->assertOk()
            ->assertSee(e(route('admin', ['tutorial' => 'admin-funding'])) . '#funding', false)
            ->assertSee('After the first request')
            ->assertDontSee('Roles and access.');

        $this->as('super_admin')->get(route('help'))->assertOk()
            ->assertSeeInOrder(['Getting started', 'Final check', 'Settings', 'IoT box monitor'])
            ->assertSee(e(route('superadmin', ['tutorial' => 'sa-accounts'])) . '#accounts', false)
            ->assertSee('The smart donation boxes, live.');
    }

    public function test_each_page_gets_its_own_tutorial_until_it_is_seen(): void
    {
        $this->mock(FundingService::class, fn ($mock) => $mock->shouldReceive('getRequestsByUser')->andReturn([]));
        $this->mock(FirebaseAdminPostRepository::class, fn ($mock) => $mock->shouldReceive('latest')->andReturn([]));

        $home = $this->tutorialsOn($this->as('normaluser')->get(route('dashboarduser')));
        $this->assertSame(['welcome'], array_column($home['tutorials'], 'id'));
        $this->assertSame([], $home['seen']);
        $this->assertNull($home['start']);

        $seen = ['tutorials_seen' => ['welcome']];
        $this->assertSame(['welcome'], $this->tutorialsOn($this->as('normaluser', $seen)->get(route('dashboarduser')))['seen']);
        // Asked for again (Help, or the old ?tour=1 link); a tutorial of another page is ignored.
        $this->assertSame('welcome', $this->tutorialsOn($this->as('normaluser', $seen)->get(route('dashboarduser', ['tutorial' => 'welcome'])))['start']);
        $this->assertSame('welcome', $this->tutorialsOn($this->as('normaluser', $seen)->get(route('dashboarduser', ['tour' => 1])))['start']);
        $this->assertNull($this->tutorialsOn($this->as('normaluser')->get(route('dashboarduser', ['tutorial' => 'sa-accounts'])))['start']);

        $settings = $this->tutorialsOn($this->as('normaluser')->get(route('settings')));
        $this->assertSame(['settings'], array_column($settings['tutorials'], 'id'));
        $this->assertSame('click', collect($home['tutorials'][0]['steps'])->last()['do']);
    }

    public function test_finishing_a_tutorial_is_remembered_on_the_account(): void
    {
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('update')->once()
            ->with('test-admin', ['tutorials_seen' => ['admin-welcome', 'admin-funding']])->andReturn([]));

        $this->as('admin', ['tutorials_seen' => ['admin-welcome']])->postJson(route('tour.complete'), ['id' => 'admin-funding'])->assertNoContent();
        // Already seen: nothing to save. Unknown: refused.
        $this->as('admin', ['tutorials_seen' => ['admin-welcome']])->postJson(route('tour.complete'), ['id' => 'admin-welcome'])->assertNoContent();
        $this->as('admin')->postJson(route('tour.complete'), ['id' => 'nope'])->assertUnprocessable();
    }
}
