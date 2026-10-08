<?php

namespace App\Services;

/**
 * The foundation's request rules (config/funding.php and the Firebase price list):
 * categories, required documents, per-person budgets and price checks.
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
     * Flat price list: key => [key, group, group_label, type, name, size, price, min, max, source, updated_at...].
     * Managed by the super admin and refreshed monthly by the AI price update (see PriceListService).
     */
    public function priceCatalog(): array
    {
        return app(PriceListService::class)->items();
    }

    /**
     * Normalise budget lines. Every line must be an item on the price list and always uses the
     * list price: admins choose items and quantities but cannot change prices or add items.
     *
     * @param  array  $lines  [['ref' => key, 'qty' => ...], ...]
     * @return array{items: array, per_person: float}
     */
    public function buildBudget(array $lines): array
    {
        $catalog = $this->priceCatalog();
        $items = [];

        foreach ($lines as $line) {
            $qty = round((float) ($line['qty'] ?? 0), 2);
            $ref = $catalog[$line['ref'] ?? ''] ?? null;
            if ($ref === null) {
                continue;
            }
            $price = round($ref['price'], 2);
            $name = trim("{$ref['name']} ({$ref['size']})");
            if ($qty <= 0 || $price <= 0) {
                continue;
            }

            $items[] = [
                'ref' => $ref['key'],
                'group' => $ref['group'],
                'name' => mb_substr($name, 0, 120),
                'qty' => $qty,
                'unit_price' => $price,
                'subtotal' => round($qty * $price, 2),
                'reference_min' => $ref['min'],
                'reference_max' => $ref['max'],
            ];
        }

        return [
            'items' => $items,
            'per_person' => round(array_sum(array_column($items, 'subtotal')), 2),
        ];
    }

    /**
     * Price check for a stored budget line: "within", "above", "below" or null when there is no reference.
     */
    public function priceCheck(array $item): ?string
    {
        if (! isset($item['reference_min'], $item['reference_max'])) {
            return null;
        }

        return match (true) {
            $item['unit_price'] > $item['reference_max'] * 1.05 => 'above',
            $item['unit_price'] < $item['reference_min'] * 0.95 => 'below',
            default => 'within',
        };
    }

    /**
     * One name per line, de-duplicated, blank lines ignored.
     */
    public function parseBeneficiaries(?string $text): array
    {
        $names = array_map(
            static fn (string $line): string => trim(preg_replace('/^\s*\d+[\.\)]\s*/', '', $line)),
            preg_split('/\r\n|\r|\n/', (string) $text)
        );

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
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

    public function maskAccountNumber(?string $number): string
    {
        $digits = preg_replace('/\s+/', '', (string) $number);

        return strlen($digits) > 4 ? str_repeat('•', strlen($digits) - 4) . substr($digits, -4) : $digits;
    }
}
