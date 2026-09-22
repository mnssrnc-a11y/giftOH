<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SuperadminUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolate UI access tests from external authentication and asset services.
        config(['auth.providers.users.driver' => 'eloquent',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->withoutVite();
    }

    public function createApplication()
    {
        $original = $_ENV['APP_URL'] ?? null;
        $_ENV['APP_URL'] = 'http://localhost';
        try {
            return parent::createApplication();
        } finally {
            if ($original === null) {
                unset($_ENV['APP_URL']);
            } else {
                $_ENV['APP_URL'] = $original;
            }
        }
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('superadmin'))->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_open_administration_preview(): void
    {
        $this->actingAs(new User(['fname' => 'Test', 'role' => 'user']))
            ->get(route('superadmin'))->assertForbidden();
    }

    public function test_admin_roles_can_render_the_complete_preview(): void
    {
        foreach (['admin', 'superadmin'] as $role) {
            $this->actingAs(new User(['fname' => 'Alex', 'lname' => 'Tester', 'role' => $role]))
                ->get(route('superadmin'))->assertOk()
                ->assertSee('Superadmin navigation')->assertSee('UI preview')
                ->assertSee('Account management')->assertSee('System settings')
                ->assertSee('System monitor')->assertSee('Fund requests')
                ->assertSee('Activity log')->assertSee('greater than 92 days');
        }
    }
}
