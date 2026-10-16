<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every peso field gets the quick amounts (+1,000 / +500 / +200 / +100, resources/js/amount-buttons.js),
 * so amounts are built with buttons instead of typed digit by digit.
 */
class AmountButtonsTest extends TestCase
{
    public function test_every_peso_field_has_the_quick_amounts(): void
    {
        $fields = [
            'resources/views/adminPage/partials/assessment-form.blade.php' => 'amount_needed',  // amount the request needs
            'resources/views/adminPage/request-show.blade.php' => 'amount',                      // amount released
            'resources/views/supperAdminPage/request-show.blade.php' => 'amount',                // amount to grant
            'resources/views/FundPage/fund-request-detail.blade.php' => 'amount_spent',          // liquidation
            'resources/js/admin-ui.js' => 'amount',                                              // the admin's decision dialog
        ];

        foreach ($fields as $file => $name) {
            preg_match_all('/<input\b[^>]*\bname="' . $name . '"[^>]*>/', file_get_contents(base_path($file)), $inputs);
            $this->assertNotEmpty($inputs[0], "{$file}: {$name} field");
            foreach ($inputs[0] as $input) {
                $this->assertStringContainsString('data-amount-buttons', $input, "{$file}: {$name} has the quick amounts");
            }
        }

        // The amount released is changed with the buttons only: it cannot be typed in or tabbed to.
        preg_match('/<input id="amount"[^>]*>/', file_get_contents(resource_path('views/adminPage/request-show.blade.php')), $released);
        $this->assertStringContainsString(' readonly', $released[0]);
        $this->assertStringContainsString('tabindex="-1"', $released[0]);
        $this->assertStringNotContainsString(' disabled', $released[0], 'it is still sent with the form');

        $script = file_get_contents(resource_path('js/amount-buttons.js'));
        $this->assertStringContainsString('const STEPS = [1000, 500, 200, 100];', $script);
        $this->assertStringContainsString("import './amount-buttons';", file_get_contents(resource_path('js/app.js')));
    }
}
