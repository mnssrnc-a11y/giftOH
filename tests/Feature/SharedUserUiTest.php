<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Services\FundingService;
use Tests\TestCase;

class SharedUserUiTest extends TestCase
{
    public function test_every_role_can_open_its_account_pages_in_its_own_layout(): void
    {
        $this->withoutVite();
        // The admin layout's pending badge reads Firebase; keep the test offline.
        $this->mock(FundingService::class, fn ($mock) => $mock->shouldReceive('getPendingRequests')->andReturn([]));

        $layouts = ['user' => 'Main navigation', 'admin' => 'Admin navigation', 'super_admin' => 'Superadmin navigation'];
        foreach ($layouts as $role => $landmark) {
            $this->actingAs(new FirebaseUser(['id' => "test-{$role}", 'fname' => 'Alex', 'lname' => 'Tester', 'role' => $role, 'phone' => '09170000000']));

            $this->get(route('settings'))->assertOk()->assertSee($landmark)
                ->assertSee(route('change-password.form'))->assertSee(route('user.edit'));
            $this->get(route('change-password.form'))->assertOk()->assertSee($landmark)->assertSee('Change your password.');
            $this->get(route('user.edit'))->assertOk()->assertSee($landmark)->assertSee('09170000000');
        }
    }
}
