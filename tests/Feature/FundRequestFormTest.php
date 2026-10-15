<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseApprovalRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Services\AiScoringService;
use App\Services\AuditLogger;
use App\Services\FundingService;
use App\Services\NotificationService;
use App\Services\SystemSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class FundRequestFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['security.uploads.engines' => ['heuristic'], 'funding.files_disk' => 'local']);
        Storage::fake('local');
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
        $this->mock(AiScoringService::class, fn ($mock) => $mock->shouldReceive('scoreAndStore'));
    }

    private function requester(): static
    {
        return $this->actingAs(new FirebaseUser(['id' => 'user-1', 'fname' => 'Maria', 'lname' => 'Santos', 'email' => 'maria@example.test', 'role' => 'normaluser']));
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF");
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'org_choice' => 'Tuloy Pampanga',
            'contact_person' => 'Maria Santos',
            'contact_email' => 'maria@example.test',
            'phone' => '09171234567',
            'street' => '12 Rizal St.',
            'barangay' => 'San Jose',
            'municipality' => 'City of San Fernando',
            'categories' => ['Medical', 'Food'],
            'purpose' => 'Medicines and food packs for the elderly residents.',
            'beneficiary_count' => 25,
            'transfer_mode' => 'e_wallet',
            'provider' => 'GCash',
            'account_name' => 'Maria Santos',
            'account_number' => '0917 123 4567',
            'formal_letter' => $this->pdf('letter.pdf'),
            'indigency_cert' => $this->pdf('indigency.pdf'),
        ], $overrides);
    }

    public function test_a_request_saves_the_joined_address_every_need_and_the_e_wallet(): void
    {
        $saved = null;
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('requestDenialReason')->andReturn(null);
        $funding->shouldReceive('getPendingRequests')->andReturn([]);
        $funding->shouldReceive('createRequest')->once()->andReturnUsing(function (array $data) use (&$saved) {
            $saved = $data;

            return $data + ['id' => 'req-9'];
        });
        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('getByUser')->andReturn([]);
            $mock->shouldReceive('notifyAdmins')->once()->with('req-9', 'new_request', 'New funding request', Mockery::pattern('/Tuloy Pampanga .*25 people/'));
        });

        $this->requester()->post(route('fund-request.store'), $this->form())
            ->assertRedirect(route('fund-request.show', 'req-9'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'under assessment'));

        $this->assertSame('12 Rizal St., San Jose, City of San Fernando, Pampanga', $saved['address']);
        $this->assertSame(['street' => '12 Rizal St.', 'barangay' => 'San Jose', 'municipality' => 'City of San Fernando', 'province' => 'Pampanga'], $saved['address_parts']);
        $this->assertSame(['Medical', 'Food'], $saved['categories']);
        $this->assertSame('Medical', $saved['category']);
        $this->assertSame(25, $saved['beneficiary_count']);
        $this->assertSame([], $saved['beneficiaries'], 'the names are listed later, at the release of funds');
        $this->assertSame(['method' => 'e_wallet', 'mode' => 'e_wallet', 'provider' => 'GCash', 'bank_name' => 'GCash', 'account_name' => 'Maria Santos', 'account_number' => '09171234567'], $saved['disbursement']);
        $this->assertSame('Tuloy Pampanga', $saved['org_name']);
    }

    public function test_every_missing_field_is_reported_at_once_with_a_clear_message(): void
    {
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('requestDenialReason')->andReturn(null);
        $funding->shouldNotReceive('createRequest');

        $this->requester()->post(route('fund-request.store'), ['org_choice' => 'other'])
            ->assertSessionHasErrors([
                'org_name_other' => 'Type the name of your organization.',
                'contact_person', 'contact_email', 'phone', 'street', 'barangay', 'municipality',
                'categories' => 'Choose at least one kind of help you need.',
                'purpose', 'beneficiary_count',
                'transfer_mode' => 'Choose how to receive the funds: bank or e-wallet.',
                'provider', 'account_name', 'account_number', 'formal_letter', 'indigency_cert',
            ]);
    }

    public function test_the_account_field_follows_the_chosen_bank_or_e_wallet(): void
    {
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('requestDenialReason')->andReturn(null);
        $funding->shouldNotReceive('createRequest');

        $this->requester()->post(route('fund-request.store'), $this->form(['transfer_mode' => 'bank', 'provider' => 'BDO Unibank', 'account_number' => 'ABC']))
            ->assertSessionHasErrors(['account_number' => 'Enter the account number (6 to 30 digits).']);
        $this->requester()->post(route('fund-request.store'), $this->form(['provider' => 'PayPal', 'account_number' => '09171234567']))
            ->assertSessionHasErrors(['account_number' => 'Enter the email address of the PayPal account.']);
        $this->requester()->post(route('fund-request.store'), $this->form(['provider' => 'BDO Unibank']))
            ->assertSessionHasErrors(['provider' => 'Choose the bank or e-wallet from the list.']);
        $this->requester()->post(route('fund-request.store'), $this->form(['org_choice' => 'Not a listed home']))
            ->assertSessionHasErrors('org_choice');
    }

    public function test_the_address_is_a_place_in_pampanga_chosen_from_the_list(): void
    {
        $funding = $this->mock(FundingService::class);
        $funding->shouldReceive('requestDenialReason')->andReturn(null);
        $funding->shouldNotReceive('createRequest');

        $this->requester()->post(route('fund-request.store'), $this->form(['municipality' => 'Quezon City']))
            ->assertSessionHasErrors(['municipality' => 'Choose a city or town in Pampanga from the list.']);
        // Dolores is a barangay of the City of San Fernando, not of Apalit.
        $this->requester()->post(route('fund-request.store'), $this->form(['municipality' => 'Apalit', 'barangay' => 'Dolores']))
            ->assertSessionHasErrors(['barangay' => 'Choose a barangay of the chosen city or town.']);

        $this->assertCount(22, \App\Support\Pampanga::towns());
        $this->assertContains('Angeles City', \App\Support\Pampanga::towns());
    }

    public function test_after_approval_the_requester_sees_the_add_person_form(): void
    {
        $approved = ['id' => 'req-1', 'user_id' => 'user-1', 'org_name' => 'Hope Home', 'status' => 'approved', 'status_name' => 'approved', 'approved_amount' => 1000,
            'category' => 'Food', 'categories' => ['Food', 'Medical'], 'budget' => ['per_person' => 500, 'people' => 2], 'beneficiary_count' => 2, 'beneficiaries' => [],
            'created_at' => now()->subDays(10)->toIso8601String(), 'approved_at' => now()->subDay()->toIso8601String(),
            'disbursement' => ['method' => 'e_wallet', 'mode' => 'e_wallet', 'provider' => 'GCash', 'account_name' => 'Hope Home', 'account_number' => '09171234567']];
        $this->mock(FirebaseFundingRepository::class, fn ($mock) => $mock->shouldReceive('findById')->with('req-1')->andReturn($approved));
        $this->mock(\App\Repositories\FirebaseMessageRepository::class, fn ($mock) => $mock->shouldReceive('forRequest')->andReturn([]));
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('getByUser')->andReturn([]));
        $this->mock(SystemSettings::class);
        $this->mock(\App\Services\FundQueue::class, fn ($mock) => $mock->shouldReceive('rowFor')->with('req-1')->andReturn(['position' => 2, 'amount' => 1000.0, 'covered' => false, 'ready' => false, 'short' => 700.0, 'raised' => 300.0, 'ahead_short' => 0.0, 'known' => true]));

        $this->requester()->get(route('fund-request.show', 'req-1'))->assertOk()
            ->assertSee('Release of funds: who will receive the help?')
            ->assertSee('Add person')
            ->assertSee('name="people[0][last]"', false)
            ->assertSee('Food packs / groceries, Medical assistance / medicines')
            ->assertSee('GCash')
            ->assertSee('₱700.00 more in donations is needed')
            ->assertSee('You are #2 in line.')
            ->assertSee('₱300.00 of ₱1,000.00 set aside for you · ₱700.00 to go')
            ->assertSee('aria-valuenow="30"', false)
            ->assertDontSee('still needed by requests approved before yours');
    }

    public function test_the_people_are_listed_after_approval_and_before_the_release(): void
    {
        $approved = ['id' => 'req-1', 'user_id' => 'user-1', 'status' => 'approved', 'status_name' => 'approved', 'approved_amount' => 1000,
            'budget' => ['per_person' => 500, 'people' => 2], 'beneficiary_count' => 2, 'beneficiaries' => [], 'disbursement' => ['method' => 'bank_transfer']];
        $repository = Mockery::mock(FirebaseFundingRepository::class);
        $repository->shouldReceive('findById')->with('req-1')->andReturn($approved);
        $saved = null;
        $repository->shouldReceive('update')->once()->andReturnUsing(function ($id, $data) use (&$saved, $approved) {
            $saved = $data;

            return array_merge($approved, $data);
        });
        $this->app->instance(FirebaseFundingRepository::class, $repository);
        $this->app->instance(FirebaseApprovalRepository::class, Mockery::mock(FirebaseApprovalRepository::class));
        $this->mock(SystemSettings::class);
        $this->mock(NotificationService::class);
        $funding = app(FundingService::class);

        $this->assertSame('awaiting_recipients', $funding->stageOf($approved)['key']);
        $this->assertNull($funding->recordDisbursement('req-1', ['amount' => 1000], 'admin-1'), 'no release before the people are listed');
        $three = [['last' => 'Cruz', 'first' => 'Ana', 'middle' => ''], ['last' => 'Reyes', 'first' => 'Ben', 'middle' => 'S'], ['last' => 'Lim', 'first' => 'Carl', 'middle' => '']];
        $this->assertNull($funding->submitBeneficiaries('req-1', $three), 'the approved budget covers 2 people');

        $updated = $funding->submitBeneficiaries('req-1', array_slice($three, 0, 2));
        $this->assertSame(['Ana Cruz', 'Ben S Reyes'], $saved['beneficiaries']);
        $this->assertSame('to_release', $funding->stageOf($updated)['key']);
    }
}
