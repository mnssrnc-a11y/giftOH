<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Each rate-limited action counts on its own: finishing tutorials or checking a transfer must not
 * use up the limit of sending a fund request or starting a transfer.
 */
class RateLimitTest extends TestCase
{
    public function test_every_rate_limited_action_has_its_own_counter(): void
    {
        $prefixes = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'throttle:')) {
                    $parts = explode(',', substr($middleware, 9));
                    $this->assertCount(3, $parts, "{$route->uri()} needs its own counter: throttle:max,minutes,name");
                    $prefixes[] = $parts[2];
                }
            }
        }

        $this->assertNotEmpty($prefixes);
        $this->assertSame(count($prefixes), count(array_unique($prefixes)), 'no two actions share a counter');
    }
}
