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
        return trim(preg_replace('/\s+/', ' ', ($person['first'] ?? '') . ' ' . ($person['middle'] ?? '') . ' ' . ($person['last'] ?? '')));
    }

    /**
     * Rows from the "Add person" fields, trimmed, without empty or repeated people.
     *
     * @return array<int, array{last: string, first: string, middle: string}>
     */
    public function cleanPeople(array $rows): array
    {
        $people = [];
        foreach ($rows as $row) {
            $person = [
                'last' => trim((string) ($row['last'] ?? '')),
                'first' => trim((string) ($row['first'] ?? '')),
                'middle' => trim((string) ($row['middle'] ?? '')),
            ];
            if ($person['last'] === '' && $person['first'] === '') {
                continue;
            }
            $people[strtolower($this->personName($person))] = $person;
        }

        return array_values($people);
    }

    public function maskAccountNumber(?string $number): string
    {
        $digits = preg_replace('/\s+/', '', (string) $number);

        return strlen($digits) > 4 ? str_repeat('•', strlen($digits) - 4) . substr($digits, -4) : $digits;
    }
}
