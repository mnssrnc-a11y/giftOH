<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\PriceListService;
use App\Services\PriceUpdateRunner;
use App\Services\PriceUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The price list (Food, Medical, Cleaning materials) is managed by the super admin only: add, edit
 * and delete items, set prices, and run the AI price update now instead of waiting for the monthly
 * run. Admins only read the list when they build a per-person budget.
 */
class SuperAdminPriceController extends Controller
{
    public function __construct(
        private PriceListService $prices,
        private AuditLogger $audit
    ) {
    }

    /**
     * Start the AI price update in its own background process (it takes a few minutes, longer
     * than a web request may run). The page follows its progress through status().
     */
    public function runUpdate(PriceUpdateRunner $runner)
    {
        if ($runner->isRunning()) {
            return $this->back()->with('alert_error', 'A price update is already running. This page updates when it finishes.');
        }
        if (! $runner->start()) {
            return $this->back()->with('alert_error', $runner->status()['message'] ?? 'The price update could not be started. Try again.');
        }
        $this->audit->record('prices', 'AI price update started by the super admin');

        return $this->back()->with('status', 'Price update started. It reads the DTI SRP bulletin, the TGP store and web search, which takes a few minutes; you will get a notification when it finishes.');
    }

    public function status(PriceUpdateRunner $runner)
    {
        return response()->json(['running' => $runner->isRunning(), 'status' => $runner->status()]);
    }

    /**
     * Add an item. With a price, the super admin's price is used; without one, the AI web search
     * looks it up.
     */
    public function store(Request $request, PriceUpdateService $updater)
    {
        $validated = $request->validate($this->itemRules() + [
            'price' => 'nullable|numeric|min:0.01|max:1000000',
        ]);

        if (isset($validated['price'])) {
            $priced = ['price' => (float) $validated['price']];
            $source = 'Set by the super admin';
            $url = null;
        } else {
            $priced = $updater->priceNewItem($validated['name'], $validated['size']);
            if ($priced === null) {
                return $this->back()->withInput()->with('alert_error', 'The AI could not find a reliable price for this item. Enter the price yourself, or try a more specific name or size.');
            }
            $source = 'Web search (Gemini · Google Search)';
            $url = $priced['url'] ?? null;
        }

        $item = $this->prices->addItem($validated['group'], $validated['name'], $validated['size'], $validated['type'] ?? null, $priced, $source, $url, Auth::id());
        $this->audit->record('prices', "Price list item added: {$item['name']} ({$item['size']}) · {$item['group_label']} · ₱" . number_format($item['price'], 2), $item['key']);

        return $this->back($item['group'])->with('status', "{$item['name']} ({$item['size']}) added to {$item['group_label']} at ₱" . number_format($item['price'], 2) . '.');
    }

    public function update(Request $request, string $key)
    {
        $validated = $request->validate($this->itemRules() + [
            'price' => 'required|numeric|min:0.01|max:1000000',
        ]);

        $result = $this->prices->updateItem($key, $validated, Auth::id());
        abort_if($result === null, 404);
        [$before, $after] = $result;

        $change = abs($after['price'] - $before['price']) >= 0.01
            ? ' · ₱' . number_format($before['price'], 2) . ' → ₱' . number_format($after['price'], 2)
            : '';
        $this->audit->record('prices', "Price list item edited: {$after['name']} ({$after['size']}){$change}", $key);

        return $this->back($after['group'])->with('status', "{$after['name']} ({$after['size']}) saved at ₱" . number_format($after['price'], 2) . '.');
    }

    public function destroy(string $key)
    {
        $item = $this->prices->deleteItem($key);
        abort_if($item === null, 404);
        $this->audit->record('prices', "Price list item deleted: {$item['name']} ({$item['size']}) · {$item['group_label']}", $key);

        return $this->back($item['group'])->with('status', "{$item['name']} ({$item['size']}) removed from the price list. Budgets already saved keep their items.");
    }

    private function itemRules(): array
    {
        return [
            'group' => ['required', Rule::in(array_keys(PriceListService::GROUPS))],
            'name' => 'required|string|max:120',
            'size' => 'required|string|max:60',
            'type' => 'nullable|string|max:80',
        ];
    }

    private function back(?string $group = null)
    {
        return redirect()->to(route('superadmin') . '#prices' . ($group ? "-{$group}" : ''));
    }
}
