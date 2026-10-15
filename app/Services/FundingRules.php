<?php

namespace App\Services;

/**
 * The foundation's request rules (config/funding.php): categories, required documents, the
 * usual per-person allocation, and where funds can be sent.
 */
class FundingRules
{
    public function categories(): array
    {
        return config('funding.categories', []);
    }

    /**
     * Canonical category key for a stored category name, matching aliases from older records.
     */
    public function categoryKey(?string $name): ?string
    {
        $needle = strtolower(trim((string) $name));
        foreach ($this->categories() as $key => $category) {
            $names = array_merge([strtolower($key)], array_map('strtolower', $category['aliases'] ?? []));
            if (in_array($needle, $names, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Documents every request must include: [field => ['label' => ..., 'hint' => ...]].
     * The category is accepted for callers that pass it; all categories share one document set.
     */
    public function requirementsFor(?string $category = null): array
    {
        return config('funding.requirements', []);
    }

    /**
     * Usual per-person allocation for the category.
     */
    public function perPersonCap(?string $category): float
    {
        $key = $this->categoryKey($category);

        return (float) ($key !== null ? $this->categories()[$key]['per_person_cap'] : config('funding.default_per_person_allocation', 500));
    }

    /**
     * Default priority score per lowercase category name (and alias).
     */
    public function defaultPriorities(): array
    {
        $priorities = [];
        foreach ($this->categories() as $key => $category) {
            foreach (array_merge([$key], $category['aliases'] ?? []) as $name) {
                $priorities[strtolower($name)] = (float) $category['priority'];
            }
        }

        return $priorities;
    }

    /**
     * Funds are only sent to an account in the receiver's name: the organization or its contact person.
     */
    public function accountNameMatches(string $accountName, array $receiverNames): bool
    {
        $normalize = static fn (string $value): string => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', strtolower($value))));
        $account = $normalize($accountName);

        foreach ($receiverNames as $name) {
            if ($account !== '' && $account === $normalize((string) $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Banks or e-wallets the requester can choose for the given mode ("bank" or "e_wallet").
     */
    public function transferProviders(?string $mode): array
    {
        return (array) config("funding.transfer_modes.{$mode}.providers", []);
    }

    /**
     * What the account field holds for this provider: "number" (bank account), "mobile"
     * (GCash / Maya number) or "email" (PayPal).
     */
    public function accountKind(?string $mode, ?string $provider): string
    {
        $kind = config("funding.transfer_modes.{$mode}.account", 'number');

        return is_array($kind) ? ($kind[$provider] ?? 'number') : $kind;
    }

    /**
     * "Medical assistance / medicines, Food packs / groceries" for the chosen category keys.
     */
    public function categoryLabels(array $keys): string
    {
        $categories = $this->categories();

        return implode(', ', array_map(fn ($key) => $categories[$key]['label'] ?? (string) $key, $keys));
    }

    /**
     * A person entered with "Add person" (last, first, middle name) as one display name.
     */
    public function personName(array $person): string
    {
        $name = trim(preg_replace('/\s+/', ' ', ($person['first'] ?? '') . ' ' . ($person['middle'] ?? '') . ' ' . ($person['last'] ?? '')));

        return empty($person['number']) ? $name : "{$name} (No. {$person['number']})";
    }

    /**
     * Rows from the "Add person" fields, trimmed, without empty or repeated people. A number is
     * kept only for people whose names are alike (see similarNameGroups).
     *
     * @return array<int, array{last: string, first: string, middle: string, number?: int}>
     */
    public function cleanPeople(array $rows): array
    {
        $rows = $this->filledPeople($rows);
        $numbered = array_merge([], ...$this->similarNameGroups($rows));
        $people = [];
        foreach ($rows as $index => $row) {
            $person = ['last' => $row['last'], 'first' => $row['first'], 'middle' => $row['middle']];
            if (in_array($index, $numbered, true) && (int) ($row['number'] ?? 0) > 0) {
                $person['number'] = (int) $row['number'];
            }
            $people[strtolower($this->personName($person))] = $person;
        }

        return array_values($people);
    }

    /**
     * People with alike names must each get a different number so they can be told apart, e.g.
     * two "Juan Dela Cruz". Field errors for the form, keyed "people.2.number".
     *
     * @return array<string, string>
     */
    public function numberingErrors(array $rows, string $field = 'people'): array
    {
        $rows = $this->filledPeople($rows);
        $errors = [];
        foreach ($this->similarNameGroups($rows) as $group) {
            $positions = array_map(fn (int $index): string => '#' . ($index + 1), $group);
            $positions = implode(', ', array_slice($positions, 0, -1)) . ' and ' . end($positions);
            $name = $this->personName(['first' => $rows[$group[0]]['first'], 'last' => $rows[$group[0]]['last']]);
            $numbers = array_map(fn (int $index): int => (int) ($rows[$index]['number'] ?? 0), $group);
            // One message per group, on the first person whose number is missing or repeated.
            $missing = array_search(true, array_map(fn (int $number): bool => $number < 1, $numbers), true);
            $repeated = array_diff_key($numbers, array_unique($numbers));
            if ($missing !== false) {
                $errors["{$field}.{$group[$missing]}.number"] = "Persons {$positions} have similar names ({$name}). Enter a number for each, like 1, 2, 3, so they can be told apart.";
            } elseif ($repeated !== []) {
                $errors["{$field}.{$group[array_key_first($repeated)]}.number"] = "Persons {$positions} have similar names ({$name}). Give each a different number; " . reset($repeated) . ' is used more than once.';
            }
        }

        return $errors;
    }

    /**
     * Groups of rows (indexes, two or more each) whose names are alike: the same first and last
     * name, ignoring case, accents, spaces and dots, and middle names that do not differ (one
     * left out, or an initial that matches). "Juan Dela Cruz", "JUAN DELACRUZ" and
     * "Juan S. Dela Cruz" are alike; "Juan Santos Dela Cruz" and "Juan Reyes Dela Cruz" are not.
     *
     * @return array<int, int[]>
     */
    public function similarNameGroups(array $rows): array
    {
        $key = fn (?string $part): string => preg_replace('/[^a-z0-9]/', '', strtolower(\Illuminate\Support\Str::ascii((string) $part)));
        $people = [];
        foreach ($rows as $index => $row) {
            $row = (array) $row;
            if ($key($row['first'] ?? '') === '' && $key($row['last'] ?? '') === '') {
                continue; // an empty row
            }
            $people[$index] = ['name' => $key($row['first'] ?? '') . '|' . $key($row['last'] ?? ''), 'middle' => $key($row['middle'] ?? '')];
        }
        if ($people === []) {
            return [];
        }
        $alike = fn (array $a, array $b): bool => $a['name'] === $b['name'] && ($a['middle'] === '' || $b['middle'] === ''
            || $a['middle'] === $b['middle'] || (strlen($a['middle']) === 1 || strlen($b['middle']) === 1) && $a['middle'][0] === $b['middle'][0]);

        // Join alike people into groups (a person with no middle name links the ones with one).
        $group = array_combine(array_keys($people), array_keys($people));
        $root = function ($index) use (&$group, &$root) {
            return $group[$index] === $index ? $index : $group[$index] = $root($group[$index]);
        };
        $indexes = array_keys($people);
        foreach ($indexes as $i => $first) {
            foreach (array_slice($indexes, $i + 1) as $second) {
                if ($alike($people[$first], $people[$second])) {
                    $group[$root($second)] = $root($first);
                }
            }
        }

        $groups = [];
        foreach ($indexes as $index) {
            $groups[$root($index)][] = $index;
        }

        return array_values(array_filter($groups, fn (array $members): bool => count($members) > 1));
    }

    /** Trimmed rows with a name, keyed by their place in the form (0, 1, 2...). */
    private function filledPeople(array $rows): array
    {
        $people = [];
        foreach (array_values($rows) as $index => $row) {
            $person = [
                'last' => trim((string) ($row['last'] ?? '')),
                'first' => trim((string) ($row['first'] ?? '')),
                'middle' => trim((string) ($row['middle'] ?? '')),
                'number' => $row['number'] ?? null,
            ];
            if ($person['last'] !== '' || $person['first'] !== '') {
                $people[$index] = $person;
            }
        }

        return $people;
    }

    public function maskAccountNumber(?string $number): string
    {
        $digits = preg_replace('/\s+/', '', (string) $number);

        return strlen($digits) > 4 ? str_repeat('•', strlen($digits) - 4) . substr($digits, -4) : $digits;
    }
}
