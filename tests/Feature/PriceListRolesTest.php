<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Services\AuditLogger;
use App\Services\FundingRules;
use App\Services\FundingService;
use App\Services\PriceListService;
use Tests\TestCase;

class PriceListRolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['security.uploads.engines' => ['heuristic']]);
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
    }

    private function as(string $role): static
    {
        return $this->actingAs(new FirebaseUser(['id' => "test-{$role}", 'fname' => 'Alex', 'lname' => 'Tester', 'role' => $role]));
    }

    private function item(array $overrides = []): array
    {
        return $overrides + [
            'key' => 'sardines', 'group' => 'food', 'group_label' => 'Food', 'type' => 'Canned goods',
            'name' => 'Sardines', 'size' => '155 g', 'price' => 33.0, 'min' => 19.0, 'max' => 33.0,
            'source' => 'Foundation interview reference', 'source_url' => null, 'matched_product' => null,
            'updated_at' => null, 'checked_at' => null, 'previous_price' => null, 'added_by_ai' => false, 'updated_by' => null,
        ];
    }

    public function test_admins_and_users_cannot_change_items_or_prices(): void
    {
        $prices = $this->mock(PriceListService::class);
        $prices->shouldNotReceive('addItem', 'updateItem', 'deleteItem');

        foreach (['admin' => 'admin', 'user' => 'dashboarduser'] as $role => $home) {
            $this->as($role)->post(route('superadmin.prices.store'), ['group' => 'food', 'name' => 'Eggs', 'size' => 'tray', 'price' => 250])->assertRedirect(route($home));
            $this->as($role)->put(route('superadmin.prices.update-item', 'sardines'), ['group' => 'food', 'name' => 'Sardines', 'size' => '155 g', 'price' => 1])->assertRedirect(route($home));
            $this->as($role)->delete(route('superadmin.prices.destroy', 'sardines'))->assertRedirect(route($home));
        }
        // The old admin price routes are gone.
        $this->as('admin')->post('/admin/prices/update')->assertNotFound();
        $this->as('admin')->post('/admin/prices/items')->assertNotFound();
    }

    public function test_super_admin_adds_edits_and_deletes_items_in_the_three_groups(): void
    {
        $prices = $this->mock(PriceListService::class);
        $prices->shouldReceive('addItem')->once()
            ->with('medical', 'Cetirizine 10 mg', 'per tablet', 'Medicine (per piece)', ['price' => 6.5], 'Set by the super admin', null, 'test-super_admin')
            ->andReturn($this->item(['key' => 'cetirizine-10-mg-per-tablet', 'group' => 'medical', 'group_label' => 'Medical', 'name' => 'Cetirizine 10 mg', 'size' => 'per tablet', 'price' => 6.5]));
        $prices->shouldReceive('updateItem')->once()
            ->withArgs(fn ($key, $changes, $by) => $key === 'sardines' && (float) $changes['price'] === 35.0 && $by === 'test-super_admin')
            ->andReturn([$this->item(), $this->item(['price' => 35.0, 'previous_price' => 33.0])]);
        $prices->shouldReceive('deleteItem')->once()->with('sardines')->andReturn($this->item());

        $this->as('super_admin')->post(route('superadmin.prices.store'), ['group' => 'medical', 'type' => 'Medicine (per piece)', 'name' => 'Cetirizine 10 mg', 'size' => 'per tablet', 'price' => 6.5])
            ->assertRedirect(route('superadmin') . '#prices-medical')->assertSessionHas('status');
        $this->as('super_admin')->put(route('superadmin.prices.update-item', 'sardines'), ['group' => 'food', 'name' => 'Sardines', 'size' => '155 g', 'price' => 35])
            ->assertRedirect(route('superadmin') . '#prices-food');
        $this->as('super_admin')->delete(route('superadmin.prices.destroy', 'sardines'))
            ->assertRedirect(route('superadmin') . '#prices-food');

        // Only Food, Medical and Cleaning materials exist.
        $this->as('super_admin')->post(route('superadmin.prices.store'), ['group' => 'education', 'name' => 'Notebook', 'size' => 'pc', 'price' => 20])
            ->assertSessionHasErrors('group');
    }

    public function test_budgets_always_use_the_list_price_and_ignore_items_not_on_the_list(): void
    {
        $this->mock(PriceListService::class, fn ($mock) => $mock->shouldReceive('items')->andReturn(['sardines' => $this->item()]));

        $budget = app(FundingRules::class)->buildBudget([
            ['ref' => 'sardines', 'qty' => 4, 'unit_price' => 1],          // tampered price
            ['ref' => 'made-up', 'qty' => 1, 'unit_price' => 5000],         // not on the list
            ['name' => 'Custom item', 'qty' => 1, 'unit_price' => 900],     // custom line
        ]);

        $this->assertCount(1, $budget['items']);
        $this->assertSame(33.0, $budget['items'][0]['unit_price']);
        $this->assertSame('food', $budget['items'][0]['group']);
        $this->assertSame(132.0, $budget['per_person']);
    }

    public function test_education_and_scholarship_assistance_is_no_longer_offered(): void
    {
        $rules = app(FundingRules::class);

        $this->assertArrayNotHasKey('Education', $rules->categories());
        $this->assertSame(['Medical', 'Food', 'Hygiene & Cleaning Supplies', 'Shelter', 'Emergency Relief', 'Community Development'], array_keys($rules->categories()));
        $this->assertSame(['formal_letter', 'indigency_cert'], array_keys($rules->requirementsFor('Education')));
        $this->assertSame(['bank_transfer'], array_keys(config('funding.disbursement_methods')));
        $this->assertFalse(app('router')->has('admin.fund-request.scholarship-suggestion'));
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
