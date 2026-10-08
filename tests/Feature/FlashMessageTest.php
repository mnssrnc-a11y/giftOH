<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FlashMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->group(function () {
            Route::get('/_test/fail', fn () => redirect('/_test/page')->with('alert_error', 'We could not email your approval code.'));
            Route::get('/_test/poll', fn () => response()->json(['ok' => true]));
            Route::get('/_test/page', fn () => (string) session('alert_error'));
        });
    }

    public function test_background_requests_do_not_consume_the_next_pages_message(): void
    {
        $this->get('/_test/fail')->assertRedirect('/_test/page');
        // The page's message refresh and AI fetches can run before the redirect is followed.
        $this->get('/_test/poll', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->getJson('/_test/poll')->assertOk();

        $this->get('/_test/page')->assertSeeText('We could not email your approval code.');
        // Shown once, then gone.
        $this->get('/_test/page')->assertDontSeeText('approval code');
    }
}
