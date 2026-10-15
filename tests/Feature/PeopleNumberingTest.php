<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Services\AuditLogger;
use App\Services\FundingRules;
use App\Services\FundingService;
use App\Services\FundQueue;
use App\Services\NotificationService;
use Tests\TestCase;

/**
 * People with alike names on a list (two "Juan Dela Cruz") must each be given a different
 * number so the foundation can tell them apart.
 */
class PeopleNumberingTest extends TestCase
{
    private function person(string $first, string $last, string $middle = '', ?int $number = null): array
    {
        return ['last' => $last, 'first' => $first, 'middle' => $middle] + ($number ? ['number' => $number] : []);
    }

    public function test_alike_names_are_found_ignoring_case_spaces_dots_and_accents(): void
    {
        $rules = new FundingRules;

        $this->assertSame([[0, 1, 3]], $rules->similarNameGroups([
            $this->person('Juan', 'Dela Cruz'),
            $this->person('JUAN', 'Delacruz', 'S.'),
            $this->person('Ana', 'Peña'),
            $this->person(' juan ', 'dela  cruz', 'Santos'),
        ]), 'a matching middle initial, or none, is alike');
        $this->assertSame([[0, 1]], $rules->similarNameGroups([$this->person('Ana', 'Peña'), $this->person('Ana', 'Pena')]));

        $this->assertSame([], $rules->similarNameGroups([
            $this->person('Juan', 'Dela Cruz', 'Santos'),
            $this->person('Juan', 'Dela Cruz', 'Reyes'),
            $this->person('Juan', 'Dela Cruz Jr.'),
            $this->person('', ''),
            $this->person('', ''),
        ]), 'different middle names, a suffix, or empty rows are not alike');
    }

    public function test_each_alike_person_needs_a_different_number(): void
    {
        $rules = new FundingRules;
        $two = fn (?int $a, ?int $b) => [$this->person('Ana', 'Cruz'), $this->person('Ben', 'Lim'), $this->person('Ana', 'Cruz', '', $a), $this->person('Ana', 'Cruz', 'M', $b)];

        $this->assertSame(['people.0.number' => 'Persons #1, #3 and #4 have similar names (Ana Cruz). Enter a number for each, like 1, 2, 3, so they can be told apart.'],
            $rules->numberingErrors([$this->person('Ana', 'Cruz')] + $two(1, 2)));
        $this->assertSame(['people.3.number' => 'Persons #1, #3 and #4 have similar names (Ana Cruz). Give each a different number; 2 is used more than once.'],
            $rules->numberingErrors(array_replace($two(2, 2), [0 => $this->person('Ana', 'Cruz', '', 1)])));
        $this->assertSame([], $rules->numberingErrors(array_replace($two(2, 3), [0 => $this->person('Ana', 'Cruz', '', 1)])));
        $this->assertSame([], $rules->numberingErrors([$this->person('Ana', 'Cruz'), $this->person('Ben', 'Lim')]), 'no numbers needed');
    }

    public function test_numbers_are_kept_only_for_alike_people_and_shown_with_the_name(): void
    {
        $rules = new FundingRules;
        $people = $rules->cleanPeople([
            $this->person('Juan', 'Dela Cruz', '', 1),
            $this->person('Juan', 'Dela Cruz', '', 2),
            $this->person('Ben', 'Lim', '', 7),
        ]);

        $this->assertSame([1, 2, null], array_map(fn ($person) => $person['number'] ?? null, $people), 'both Juans are kept');
        $this->assertSame(['Juan Dela Cruz (No. 1)', 'Juan Dela Cruz (No. 2)', 'Ben Lim'], array_map([$rules, 'personName'], $people));
    }

    private function sendList(array $people, ?array $expectSaved = null)
    {
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
        $this->mock(FundingService::class, function ($mock) use ($expectSaved) {
            $mock->shouldReceive('getRequestById')->with('req-1')->andReturn(['id' => 'req-1', 'user_id' => 'user-1', 'org_name' => 'Hope Home']);
            $mock->shouldReceive('approvedPeople')->andReturn(5);
            $expectSaved === null
                ? $mock->shouldNotReceive('submitBeneficiaries')
                : $mock->shouldReceive('submitBeneficiaries')->once()->with('req-1', $expectSaved)->andReturn(['id' => 'req-1']);
        });
        $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('notifyAdmins', 'notifySuperAdmins'));
        $this->mock(FundQueue::class, fn ($mock) => $mock->shouldReceive('refresh'));

        return $this->actingAs(new FirebaseUser(['id' => 'user-1', 'fname' => 'Maria', 'lname' => 'Santos', 'role' => 'normaluser']))
            ->from(route('fund-request.show', 'req-1'))
            ->post(route('fund-request.beneficiaries', 'req-1'), ['people' => $people]);
    }

    public function test_the_list_is_refused_until_alike_people_are_numbered(): void
    {
        $this->sendList([$this->person('Juan', 'Dela Cruz'), $this->person('Juan', 'Dela Cruz')])
            ->assertRedirect(route('fund-request.show', 'req-1'))
            ->assertSessionHasErrors(['people.0.number' => 'Persons #1 and #2 have similar names (Juan Dela Cruz). Enter a number for each, like 1, 2, 3, so they can be told apart.'])
            ->assertSessionHasInput('people');
    }

    public function test_numbered_alike_people_are_both_saved(): void
    {
        $this->sendList([$this->person('Juan', 'Dela Cruz', '', 1), $this->person('Juan', 'Dela Cruz', '', 2), $this->person('Ana', 'Lim')], [
            ['last' => 'Dela Cruz', 'first' => 'Juan', 'middle' => '', 'number' => 1],
            ['last' => 'Dela Cruz', 'first' => 'Juan', 'middle' => '', 'number' => 2],
            ['last' => 'Lim', 'first' => 'Ana', 'middle' => ''],
        ])->assertSessionHasNoErrors();
    }

    public function test_the_form_shows_the_number_box_for_alike_names(): void
    {
        $html = view('partials.people-list', ['name' => 'people', 'limit' => 5, 'people' => [
            $this->person('Juan', 'Dela Cruz'), $this->person('Ana', 'Lim'), $this->person('juan', 'delacruz', '', 2),
        ]])->render();

        $this->assertStringContainsString('class="people-list has-numbers"', $html);
        $this->assertSame(2, substr_count($html, 'class="people-row needs-number"'));
        $this->assertStringContainsString('persons #1 and #3 (Juan Dela Cruz)', $html);
        $this->assertStringContainsString('name="people[2][number]" value="2"', $html);

        $plain = view('partials.people-list', ['name' => 'people', 'limit' => 5, 'people' => [$this->person('Ana', 'Lim')]])->render();
        $this->assertStringNotContainsString('has-numbers', $plain);
        $this->assertMatchesRegularExpression('/data-people-alike role="status"\s+hidden/', $plain);
    }
}
