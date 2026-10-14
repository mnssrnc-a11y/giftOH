<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Services\AuditLogger;
use App\Services\FundingRules;
use App\Services\FundingService;
use Tests\TestCase;

class FundingRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
    }

    private function as(string $role): static
    {
        return $this->actingAs(new FirebaseUser(['id' => "test-{$role}", 'fname' => 'Alex', 'lname' => 'Tester', 'role' => $role]));
    }

    public function test_education_and_scholarship_assistance_is_no_longer_offered(): void
    {
        $rules = app(FundingRules::class);

        $this->assertArrayNotHasKey('Education', $rules->categories());
        $this->assertSame(['Medical', 'Food', 'Hygiene & Cleaning Supplies', 'Shelter', 'Emergency Relief', 'Community Development'], array_keys($rules->categories()));
        $this->assertSame(['formal_letter', 'indigency_cert'], array_keys($rules->requirementsFor('Education')));
        $this->assertSame(['bank_transfer', 'e_wallet'], array_keys(config('funding.disbursement_methods')));
        $this->assertFalse(app('router')->has('admin.fund-request.scholarship-suggestion'));
    }

    public function test_the_price_list_and_its_ai_web_search_are_gone(): void
    {
        foreach (['superadmin.prices.update', 'superadmin.prices.status', 'superadmin.prices.store', 'superadmin.prices.update-item', 'superadmin.prices.destroy', 'admin.fund-request.budget'] as $route) {
            $this->assertFalse(app('router')->has($route), "{$route} should be removed");
        }
        $this->assertArrayNotHasKey('prices:update', \Illuminate\Support\Facades\Artisan::all());
        $this->assertNull(config('ai.search'));
        $this->assertFalse(method_exists(\App\Services\Ai\AiClient::class, 'searchJson'));
    }

    public function test_admins_add_verification_notes_and_requesters_cannot(): void
    {
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('getPendingRequests')->andReturn([]);
        $funding->shouldReceive('getRequestById')->with('req-1')->andReturn(['id' => 'req-1', 'org_name' => 'Sample Home']);
        $funding->shouldReceive('addStaffNote')->once()
            ->withArgs(fn ($id, $body) => $id === 'req-1' && $body === 'Barangay confirmed the indigency certificate.')
            ->andReturn(['body' => 'Barangay confirmed the indigency certificate.']);

        $this->as('admin')->post(route('admin.fund-request.notes', 'req-1'), ['note' => '  Barangay confirmed the indigency certificate.  '])
            ->assertRedirect(route('admin.fund-request.show', 'req-1') . '#notes')->assertSessionHas('status');
        $this->as('admin')->post(route('admin.fund-request.notes', 'req-1'), ['note' => ''])->assertSessionHasErrors('note');
        $this->as('user')->post(route('admin.fund-request.notes', 'req-1'), ['note' => 'x'])->assertRedirect(route('dashboarduser'));
    }
}
