<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The price list used for per-person budgets, kept in Firebase grouped by kind of goods:
 *
 *   price_list/{group}/label             "Food" | "Medical" | "Cleaning materials"
 *   price_list/{group}/items/{key}       name, size, type, price, min, max, source, updated_at...
 *
 * Only the super admin changes it (add, edit, delete items and set prices), together with the
 * monthly AI price update (DTI SRP bulletin, TGP online store, AI web search). Admins only read it
 * when they build a per-person budget. config/price_reference.php seeds an empty list.
 */
class PriceListService
{
    public const GROUPS = [
        'food' => 'Food',
        'medical' => 'Medical',
        'cleaning' => 'Cleaning materials',
    ];

    private const CACHE_KEY = 'price-list';

    private ?array $cache = null;

    public function __construct(
        private FirebaseService $firebase
    ) {
    }

    /**
     * Flat list, key => [key, group, group_label, type, name, size, price, min, max, source, ...],
     * ordered by group, type and name.
     */
    public function items(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $stored = (array) (Cache::get(self::CACHE_KEY) ?? $this->root()->getValue() ?? []);
            if ($stored === []) {
                $stored = $this->seed();
            } elseif ($this->isFlat($stored)) {
                $stored = $this->migrateFlat($stored);
            }
        } catch (\Throwable $exception) {
            Log::warning('Price list could not be read from Firebase; using the bundled reference.', ['error' => $exception->getMessage()]);
            $stored = $this->fromConfig();
        }

        Cache::put(self::CACHE_KEY, $stored, 600);

        $items = [];
        foreach (self::GROUPS as $group => $label) {
            foreach ((array) ($stored[$group]['items'] ?? []) as $key => $item) {
                if (is_array($item)) {
                    $items[$key] = $this->normalize((string) $key, $group, $item);
                }
            }
        }
        $order = array_flip(array_keys(self::GROUPS));
        uasort($items, static fn (array $a, array $b): int => [$order[$a['group']], $a['type'], $a['name']] <=> [$order[$b['group']], $b['type'], $b['name']]);

        return $this->cache = $items;
    }

    /**
     * group => ['key' => ..., 'label' => ..., 'items' => [...]] for every group, including empty ones.
     */
    public function grouped(): array
    {
        $groups = [];
        foreach (self::GROUPS as $group => $label) {
            $groups[$group] = ['key' => $group, 'label' => $label, 'items' => []];
        }
        foreach ($this->items() as $item) {
            $groups[$item['group']]['items'][] = $item;
        }

        return $groups;
    }

    public function find(?string $key): ?array
    {
        return $key ? ($this->items()[$key] ?? null) : null;
    }

    /**
     * Apply prices found by the monthly updater. Returns the keys that actually changed.
     *
     * @param  array  $updates  key => ['price' => float, 'min' => float, 'max' => float, 'matched' => string]
     */
    public function applyUpdates(array $updates, string $source, ?string $sourceUrl): array
    {
        $items = $this->items();
        $changed = [];
        $now = now()->toIso8601String();

        foreach ($updates as $key => $update) {
            if (! isset($items[$key])) {
                continue;
            }
            $current = $items[$key];
            $data = [
                'price' => round((float) $update['price'], 2),
                'min' => round((float) ($update['min'] ?? $update['price']), 2),
                'max' => round((float) ($update['max'] ?? $update['price']), 2),
                'source' => $source,
                'source_url' => $sourceUrl,
                'matched_product' => mb_substr((string) ($update['matched'] ?? ''), 0, 200) ?: null,
                'checked_at' => $now,
            ];
            if (abs($data['price'] - $current['price']) >= 0.01) {
                $data['previous_price'] = $current['price'];
                $data['updated_at'] = $now;
                $changed[] = $key;
            }
            $this->itemRef($current['group'], $key)->update($data);
        }
        $this->changed();

        return $changed;
    }

    /**
     * Super admin adds an item. $priced comes from the super admin's own price, or from AI web search.
     *
     * @param  array  $priced  ['price' => float, 'min' => ?float, 'max' => ?float, 'matched' => ?string]
     */
    public function addItem(string $group, string $name, string $size, ?string $type, array $priced, string $source, ?string $sourceUrl, string|int|null $by = null): array
    {
        $this->assertGroup($group);
        $key = $this->uniqueKey(Str::slug("{$name} {$size}") ?: Str::lower(Str::random(8)));
        $now = now()->toIso8601String();
        $price = round((float) $priced['price'], 2);
        $item = [
            'name' => $name, 'size' => $size, 'type' => $type ?: self::GROUPS[$group],
            'price' => $price,
            'min' => round((float) ($priced['min'] ?? $price), 2),
            'max' => round((float) ($priced['max'] ?? $price), 2),
            'source' => $source, 'source_url' => $sourceUrl,
            'matched_product' => $priced['matched'] ?? null,
            'updated_at' => $now, 'checked_at' => $now,
            'added_by_ai' => $by === null,
            'updated_by' => $by !== null ? (string) $by : null,
        ];
        $this->itemRef($group, $key)->set($item);
        $this->changed();

        return $this->normalize($key, $group, $item);
    }

    /**
     * Super admin edits an item: name, size, type, price, or moves it to another group.
     * Returns [before, after], or null when the item does not exist.
     */
    public function updateItem(string $key, array $changes, string|int $by): ?array
    {
        $current = $this->find($key);
        if ($current === null) {
            return null;
        }
        $group = $changes['group'] ?? $current['group'];
        $this->assertGroup($group);

        $price = round((float) ($changes['price'] ?? $current['price']), 2);
        $now = now()->toIso8601String();
        $item = [
            'name' => $changes['name'] ?? $current['name'],
            'size' => $changes['size'] ?? $current['size'],
            'type' => ($changes['type'] ?? null) ?: $current['type'],
            'price' => $price,
            // The reference range always contains the price the super admin set.
            'min' => round(min($current['min'] ?: $price, $price), 2),
            'max' => round(max($current['max'], $price), 2),
            'source' => abs($price - $current['price']) >= 0.01 ? 'Set by the super admin' : $current['source'],
            'source_url' => abs($price - $current['price']) >= 0.01 ? null : $current['source_url'],
            'matched_product' => $current['matched_product'],
            'previous_price' => abs($price - $current['price']) >= 0.01 ? $current['price'] : $current['previous_price'],
            'updated_at' => $now,
            'checked_at' => $current['checked_at'],
            'added_by_ai' => $current['added_by_ai'],
            'updated_by' => (string) $by,
        ];

        if ($group !== $current['group']) {
            $this->itemRef($current['group'], $key)->remove();
        }
        $this->itemRef($group, $key)->set($item);
        $this->changed();

        return [$current, $this->normalize($key, $group, $item)];
    }

    public function deleteItem(string $key): ?array
    {
        $current = $this->find($key);
        if ($current === null) {
            return null;
        }
        $this->itemRef($current['group'], $key)->remove();
        $this->changed();

        return $current;
    }

    public function recordRun(array $run): void
    {
        $this->firebase->getDatabase()->getReference('price_updates')->push(array_merge($run, ['ran_at' => now()->toIso8601String()]));
        Cache::forget('price-list-last-run');
    }

    /** Call after any write to price_list. */
    private function changed(): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY);
    }

    public function lastRun(): ?array
    {
        try {
            $runs = (array) (Cache::remember('price-list-last-run', 600, fn () => $this->firebase->getDatabase()->getReference('price_updates')->orderByKey()->limitToLast(1)->getValue() ?? []) ?? []);

            return $runs ? (array) end($runs) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function root(): mixed
    {
        return $this->firebase->getDatabase()->getReference('price_list');
    }

    private function itemRef(string $group, string $key): mixed
    {
        return $this->firebase->getDatabase()->getReference("price_list/{$group}/items/{$key}");
    }

    private function assertGroup(string $group): void
    {
        if (! isset(self::GROUPS[$group])) {
            throw new \InvalidArgumentException("Unknown price list group: {$group}");
        }
    }

    private function uniqueKey(string $base): string
    {
        $items = $this->items();
        $key = $base;
        for ($i = 2; isset($items[$key]); $i++) {
            $key = "{$base}-{$i}";
        }

        return $key;
    }

    private function seed(): array
    {
        $groups = $this->fromConfig();
        $this->root()->set($groups);
        $this->changed();

        return $groups;
    }

    /**
     * Before the groups, the list was flat: price_list/{key} => [group: "Rice (per kilo)", name, ...].
     */
    private function isFlat(array $stored): bool
    {
        foreach ($stored as $value) {
            if (is_array($value) && isset($value['name'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * One-time move of the flat list into the three groups, keeping every price and its history.
     * The old group name ("Rice (per kilo)", "Medicine (per piece)", ...) becomes the item's type.
     */
    private function migrateFlat(array $stored): array
    {
        $groups = [];
        foreach (self::GROUPS as $group => $label) {
            $groups[$group] = ['label' => $label, 'items' => []];
        }
        foreach ($stored as $key => $item) {
            if (! is_array($item) || ! isset($item['name'])) {
                continue;
            }
            $type = (string) ($item['group'] ?? '');
            $group = $this->groupForType($type);
            unset($item['group']);
            $groups[$group]['items'][$key] = $item + ['type' => $type ?: self::GROUPS[$group]];
        }
        $this->root()->set($groups);
        $this->changed();
        Log::info('Price list moved into the Food / Medical / Cleaning materials groups.', ['items' => count($stored)]);

        return $groups;
    }

    private function groupForType(string $type): string
    {
        $type = Str::lower($type);

        return match (true) {
            Str::contains($type, ['medicine', 'medical', 'vitamin', 'tablet', 'syrup']) => 'medical',
            Str::contains($type, ['clean', 'detergent', 'bleach', 'soap', 'hygiene']) => 'cleaning',
            default => 'food',
        };
    }

    /**
     * Initial list from the foundation interview, in the Firebase layout. The budgeting price is the
     * top of the range, so a beneficiary can buy the item at any store.
     */
    private function fromConfig(): array
    {
        $groups = [];
        foreach (self::GROUPS as $group => $label) {
            $groups[$group] = ['label' => $label, 'items' => []];
            foreach ((array) config("price_reference.{$group}.types", []) as $type => $typeItems) {
                foreach ($typeItems as $key => [$name, $size, $min, $max]) {
                    $groups[$group]['items'][$key] = [
                        'name' => $name, 'size' => $size, 'type' => $type,
                        'price' => (float) $max, 'min' => (float) $min, 'max' => (float) $max,
                        'source' => 'Foundation interview reference', 'source_url' => null,
                        'updated_at' => null,
                    ];
                }
            }
        }

        return $groups;
    }

    private function normalize(string $key, string $group, array $item): array
    {
        return [
            'key' => $key,
            'group' => $group,
            'group_label' => self::GROUPS[$group],
            'type' => (string) ($item['type'] ?? self::GROUPS[$group]),
            'name' => (string) ($item['name'] ?? $key),
            'size' => (string) ($item['size'] ?? ''),
            'price' => (float) ($item['price'] ?? $item['max'] ?? 0),
            'min' => (float) ($item['min'] ?? $item['price'] ?? 0),
            'max' => (float) ($item['max'] ?? $item['price'] ?? 0),
            'source' => $item['source'] ?? null,
            'source_url' => $item['source_url'] ?? null,
            'matched_product' => $item['matched_product'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
            'checked_at' => $item['checked_at'] ?? null,
            'previous_price' => isset($item['previous_price']) ? (float) $item['previous_price'] : null,
            'added_by_ai' => (bool) ($item['added_by_ai'] ?? false),
            'updated_by' => $item['updated_by'] ?? null,
        ];
    }
}
