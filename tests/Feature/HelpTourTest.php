<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseAdminPostRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\FundingService;
use App\Services\NotificationService;
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

    public function test_help_explains_the_process_and_each_role_sees_its_own_part(): void
    {
        $this->get(route('help'))->assertRedirect(route('login'));

        $this->as('normaluser')->get(route('help'))->assertOk()
            ->assertSee('How Gift of Hope works')
            ->assertSee('The requester lists the people')
            ->assertSee(route('dashboarduser', ['tour' => 1]), false)
            ->assertSee('Why was my account name refused?');

        $this->as('admin')->get(route('help'))->assertOk()
            ->assertSee(route('admin', ['tour' => 1]), false)
            ->assertSee('Why can&#039;t I approve a request?', false);

        $this->as('super_admin')->get(route('help'))->assertOk()
            ->assertSee(route('superadmin', ['tour' => 1]), false)
            ->assertSee('Why can&#039;t I send a transfer?', false);
    }

    public function test_the_tour_starts_by_itself_once_and_again_on_request(): void
    {
        $this->mock(FundingService::class, fn ($mock) => $mock->shouldReceive('getRequestsByUser')->andReturn([]));
        $this->mock(FirebaseAdminPostRepository::class, fn ($mock) => $mock->shouldReceive('latest')->andReturn([]));
        $tour = fn ($response) => json_decode(str($response->getContent())->between('window.giftOfHopeTour = ', ';</script>'), true);

        $new = $tour($this->as('normaluser')->get(route('dashboarduser')));
        $this->assertTrue($new['autostart']);
        $this->assertSame('Welcome to Gift of Hope', $new['steps'][0]['title']);

        $seen = ['tour_version' => config('tour.version')];
        $this->assertFalse($tour($this->as('normaluser', $seen)->get(route('dashboarduser')))['autostart']);
        $this->assertTrue($tour($this->as('normaluser', $seen)->get(route('dashboarduser', ['tour' => 1])))['autostart']);
        // Only the home page starts it.
        $this->assertFalse($tour($this->as('normaluser')->get(route('help')))['autostart']);
    }

    public function test_finishing_the_tour_is_remembered_on_the_account(): void
    {
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('update')->once()->with('test-admin', ['tour_version' => config('tour.version')])->andReturn([]));

        $this->as('admin')->post(route('tour.complete'))->assertNoContent();
    }
}
