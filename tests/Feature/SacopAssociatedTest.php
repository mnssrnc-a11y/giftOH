<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseAdminPostRepository;
use App\Repositories\FirebaseFundingRepository;
use App\Repositories\FirebaseMessageRepository;
use App\Repositories\FirebaseUserRepository;
use App\Services\FundingRules;
use App\Services\FundingService;
use App\Services\IotService;
use App\Services\NotificationService;
use App\Services\SystemSettings;
use App\Support\SacopAssociated;
use Tests\TestCase;

/**
 * Organizations associated with SACOP: the landing page slideshow that visitors can check an
 * organization against, the organizations offered in the fund request form, and the mark staff
 * see on their requests.
 */
class SacopAssociatedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_organization_links_to_the_sacop_page_that_shows_it(): void
    {
        $organizations = SacopAssociated::all();
        $this->assertCount(12, $organizations);
        foreach ($organizations as $org) {
            $this->assertStringStartsWith('https://sacop.ph/', $org['source'], $org['name']);
            $this->assertStringNotContainsString('utm_', $org['source'], 'no tracking parameters');
            $this->assertNotEmpty($org['photo'], "{$org['name']} has a photo");
            if (str_starts_with($org['photo'], 'https://')) {
                $this->assertMatchesRegularExpression('#^https://i[0-2]\.wp\.com/sacop\.ph/wp-content/uploads/#', $org['photo'], 'photos posted on sacop.ph');
                $this->assertNotEmpty($org['credit'], "{$org['name']}: the photo says where it is from");
            } else {
                $this->assertFileExists(public_path($org['photo']));
                $this->assertNotEmpty($org['alt'], "{$org['name']}: the photo says what it shows");
            }
        }
        $this->assertSame(count($organizations), count(array_unique(SacopAssociated::names())));
    }

    public function test_the_landing_page_shows_them_as_a_slideshow(): void
    {
        $this->mock(FirebaseAdminPostRepository::class, fn ($mock) => $mock->shouldReceive('latest')->andReturn([]));

        $response = $this->get(route('landing'))->assertOk()
            ->assertSee('id="associated"', false)
            ->assertSee('href="#associated"', false)
            ->assertSee('Associated with SACOP')
            ->assertSee('aria-label="Organizations associated with SACOP"', false)
            ->assertSee('✓ Associated with SACOP')
            ->assertSee('Next organization')
            ->assertSee('https://sacop.ph/resettlements/', false)
            ->assertSee('https://i1.wp.com/sacop.ph/wp-content/uploads/2017/09/Res-1.jpg', false)
            ->assertSee("Photo: one of SACOP's resettlement communities (sacop.ph)")
            ->assertSee('SACOP also serves')->assertSee('Students from disadvantaged families')
            ->assertDontSee('partner', false);
        foreach (SacopAssociated::names() as $name) {
            $response->assertSee($name);
        }
        // The parish photos are served from this site; each photo has a plain card under it in case it cannot load.
        $response->assertSee(asset('images/associated/lords-annunciation-parish.webp'), false)
            ->assertSee('alt="The Lord&#039;s Annunciation Parish church"', false)
            ->assertSee('Photo: richardbuan.com')
            ->assertSee('<span>LA</span><small>Parish</small>', false);
    }

    public function test_the_request_form_offers_them_and_accepts_them(): void
    {
        $this->mock(FundingService::class, fn ($mock) => $mock->shouldReceive('requestDenialReason')->andReturn(null));
        $requester = new FirebaseUser(['id' => 'user-1', 'fname' => 'Maria', 'lname' => 'Santos', 'email' => 'maria@example.test', 'role' => 'normaluser']);

        $this->actingAs($requester)->get(route('fund-request'))->assertOk()
            ->assertSeeInOrder(['<optgroup label="Associated with SACOP">', 'Promised Land Resettlement Community', '</optgroup>', '<optgroup label="Charity homes">', 'Tuloy Pampanga'], false);

        $this->actingAs($requester)->post(route('fund-request.store'), ['org_choice' => 'Wenceslao Village'])
            ->assertSessionHasErrors('contact_person')
            ->assertSessionDoesntHaveErrors('org_choice');
    }

    public function test_they_are_recognised_and_their_short_names_match_the_account(): void
    {
        $this->assertSame('Parish', SacopAssociated::find('  san jose   PARISH ')['kind']);
        $this->assertNull(SacopAssociated::find('Hope Home'));
        $this->assertNull(SacopAssociated::find(null));

        $rules = new FundingRules;
        $sapa = 'San Lorenzo Ruiz Active Peoples Association, Inc. (SAPA, Inc.)';
        $this->assertTrue($rules->accountNameMatches('San Lorenzo Ruiz Active Peoples Association Inc', [$sapa, 'Juan Cruz']));
        $this->assertTrue($rules->accountNameMatches('SAPA Inc.', [$sapa, 'Juan Cruz']));
        $this->assertFalse($rules->accountNameMatches('Another Group', [$sapa, 'Juan Cruz']));
    }

    public function test_staff_see_that_a_request_comes_from_an_organization_associated_with_sacop(): void
    {
        $request = ['id' => 'req-1', 'user_id' => 'user-1', 'org_name' => 'Mary Land Resettlement Community', 'status' => 'under review', 'category' => 'Food',
            'beneficiary_count' => 10, 'created_at' => now()->subDays(5)->toIso8601String(),
            'disbursement' => ['method' => 'e_wallet', 'mode' => 'e_wallet', 'provider' => 'GCash', 'account_name' => 'Juan Cruz', 'account_number' => '09171234567']];
        $this->mock(FirebaseFundingRepository::class, function ($mock) use ($request) {
            $mock->shouldReceive('findById')->with('req-1')->andReturn($request);
            $mock->shouldReceive('all')->andReturn([$request]);
            $mock->shouldReceive('findByUserId')->andReturn([$request]);
        });
        $this->mock(FirebaseUserRepository::class, fn ($mock) => $mock->shouldReceive('findById')->andReturn(['id' => 'user-1', 'fname' => 'Juan', 'lname' => 'Cruz']));
        $this->mock(FirebaseMessageRepository::class, fn ($mock) => $mock->shouldReceive('forRequest')->andReturn([]));
        $this->mock(IotService::class, fn ($mock) => $mock->shouldReceive('getAvailableFunds')->andReturn(50000.0));
        $this->mock(SystemSettings::class);
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('getByUser')->andReturn([]));
        $this->mock(\App\Services\FundQueue::class, fn ($mock) => $mock->shouldReceive('rowFor')->andReturn(null));
        $this->mock(\App\Services\TransactionLog::class, fn ($mock) => $mock->shouldReceive('latest')->andReturn([]));

        $this->actingAs(new FirebaseUser(['id' => 'boss-1', 'fname' => 'Sam', 'lname' => 'Boss', 'role' => 'super_admin']))
            ->get(route('superadmin.fund-request.show', 'req-1'))->assertOk()
            ->assertSee('✓ Associated with SACOP · Resettlement community ↗')
            ->assertSee('href="https://sacop.ph/resettlements/"', false);
    }
}
