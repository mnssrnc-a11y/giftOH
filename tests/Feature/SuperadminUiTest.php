<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use Tests\TestCase;

class SuperadminUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function account(string $role, bool $active = true): FirebaseUser
    {
        return new FirebaseUser(['id' => "test-{$role}", 'fname' => 'Alex', 'lname' => 'Tester', 'role' => $role, 'is_active' => $active]);
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('superadmin'))->assertRedirect(route('login'));
    }

    public function test_other_roles_are_sent_back_to_their_own_workspace(): void
    {
        $this->actingAs($this->account('user'))->get(route('superadmin'))->assertRedirect(route('dashboarduser'));
        $this->actingAs($this->account('admin'))->get(route('superadmin'))->assertRedirect(route('admin'));
    }

    public function test_disabled_accounts_are_signed_out(): void
    {
        $this->actingAs($this->account('super_admin', active: false))
            ->get(route('superadmin'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
