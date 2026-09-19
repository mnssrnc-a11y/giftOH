<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SharedUserUiTest extends TestCase
{
    public function test_shared_user_views_render_with_all_navigation_targets(): void
    {
        config(['auth.providers.users.driver' => 'eloquent']);
        $this->withoutVite();
        $this->actingAs(new User(['fname' => 'Alex', 'lname' => 'Tester', 'role' => 'admin']));

        foreach (['users.dashboarduser', 'users.user', 'pages.settings'] as $view) {
            $this->view($view)->assertSee('Main navigation')->assertSee('Admin workspace')
                ->assertSee('Superadmin workspace')->assertSee('Ready to sign out?');
        }
        foreach (['groups', 'fundraisers', 'request-status', 'activity'] as $screen) {
            $this->view('users.flow', compact('screen'))->assertSee('Main navigation')
                ->assertSee('UI preview');
        }
    }
}
