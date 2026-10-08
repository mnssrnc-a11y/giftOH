<?php

namespace App\Services;

use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Monthly price update. Reads the official sources, lets the free AI providers match them to the
 * price list, and applies the new prices (admins cannot edit prices by hand):
 *  - goods: latest DTI SRP bulletin (PDF) for basic necessities and prime commodities;
 *  - medicine: TGP online store search results (pack prices converted to unit prices);
 *  - anything left: Gemini web search (Google Search grounding, free tier).
 */
class PriceUpdateService
{
    private array $log = [];

    public function __construct(
        private PriceListService $prices,
        private AiClient $ai,
        private AuditLogger $audit
    ) {
    }

    /**
     * @return array{updated: array, unchanged: int, flagged: array, sources: array}
     */
    public function run(bool $dryRun = false): array
    {
        $this->log = [];
        $items = $this->prices->items();
        $found = [];      // key => ['price','min','max','matched','source','url']
        $sources = [];

        // 1. DTI SRP bulletin for goods.
        $goods = array_filter($items, fn (array $item): bool => in_array($item['group'], config('pricing.dti_groups'), true));
        $sources['dti'] = $this->fromDti($goods, $found);

        // 2. TGP store for medicine.
        $medicine = array_filter($items, fn (array $item): bool => in_array($item['group'], config('pricing.tgp_groups'), true));
        $sources['tgp'] = $this->fromTgp($medicine, $found);

        // 3. Web search for whatever is still unmatched.
        $remaining = array_diff_key($items, $found);
        $sources['search'] = $this->fromWebSearch($remaining, $found);

        // Validate and apply.
        $flagged = [];
        $byOrigin = [];
        foreach ($found as $key => $price) {
            $current = $items[$key]['price'];
            $ratio = $current > 0 ? $price['price'] / $current : 1;
            $limit = (float) config('pricing.max_change_ratio', 4);
            if ($price['price'] <= 0 || $ratio > $limit || $ratio < 1 / $limit) {
                $flagged[] = ['key' => $key, 'name' => $items[$key]['name'], 'reason' => sprintf('Found ₱%.2f vs current ₱%.2f — left unchanged for review', $price['price'], $current)];
                continue;
            }
            $byOrigin[$price['source'] . '|' . ($price['url'] ?? '')][$key] = $price;
        }

        $updated = [];
        if (! $dryRun) {
            foreach ($byOrigin as $origin => $updates) {
                [$source, $url] = explode('|', $origin, 2);
                $updated = array_merge($updated, $this->prices->applyUpdates($updates, $source, $url ?: null));
            }
        }

        $summary = [
            'updated' => $updated,
            'checked' => count($found) - count($flagged),
            'unchanged' => count($items) - count($updated),
            'not_found' => array_values(array_map(fn (array $item): string => $item['name'], array_diff_key($items, $found))),
            'flagged' => $flagged,
            'sources' => $sources,
            'dry_run' => $dryRun,
        ];

        if (! $dryRun) {
            $this->prices->recordRun($summary);
            $this->audit->record('prices', 'Monthly price update: ' . count($updated) . ' price(s) changed', null, [
                'checked' => $summary['checked'], 'not_found' => count($summary['not_found']), 'flagged' => count($flagged),
            ]);
        }

        return $summary;
    }

    /**
     * Price a new item with web search (admin asked for an item not yet on the list).
     */
    public function priceNewItem(string $name, string $size): ?array
    {
        $answer = $this->ai->searchJson(
            'You find current retail prices in the Philippines for a charity foundation\'s budgeting. Use official or major retail sources (DTI SRP, TGP, Mercury Drug, Puregold, SM Markets).',
            "Item: {$name}\nSize/unit: {$size}\nReturn JSON: {\"price\": <typical price in PHP for this size>, \"min\": <lowest>, \"max\": <highest>, \"matched\": \"<product and store you based this on>\"}"
        );
        $data = $answer['data'] ?? null;
        if (! is_array($data) || ! is_numeric($data['max'] ?? $data['price'] ?? null)) {
            return null;
        }

        $min = (float) ($data['min'] ?? $data['price']);
        $max = (float) ($data['max'] ?? $data['price']);

        return ['price' => max($min, $max), 'min' => min($min, $max), 'max' => max($min, $max), 'matched' => (string) ($data['matched'] ?? ''), 'url' => $answer['sources'][0] ?? null];
    }

    private function fromDti(array $goods, array &$found): array
    {
        if ($goods === []) {
            return ['status' => 'skipped'];
        }

        try {
            $page = Http::timeout(30)->withUserAgent(config('pricing.user_agent'))->get(config('pricing.dti_page'))->body();
            preg_match_all('/https?:\/\/[^"\'\s>]+\.pdf/i', html_entity_decode($page), $matches);
            $pdfUrl = collect($matches[0])->first(fn (string $url): bool => (bool) preg_match('/srp|bnpc/i', $url));
            if (! $pdfUrl) {
                return ['status' => 'failed', 'message' => 'No SRP bulletin link found on the DTI page'];
            }

            $pdf = Http::timeout(90)->withUserAgent(config('pricing.user_agent'))->get($pdfUrl)->body();
            $text = (new PdfParser())->parseContent($pdf)->getText();
        } catch (\Throwable $exception) {
            Log::warning('DTI price source failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'message' => $exception->getMessage()];
        }

        $list = collect($goods)->map(fn (array $item): string => "{$item['key']}: {$item['name']} — {$item['size']}")->implode("\n");
        $answer = $this->ai->json(
            'You read a DTI (Philippines) Suggested Retail Price bulletin. Its text is extracted from a multi-column PDF, so items, sizes and prices may be interleaved; pair each product with its own size and SRP carefully.',
            "Bulletin text:\n" . mb_substr($text, 0, 30000) . "\n\nPrice list items (key: name — size):\n{$list}\n\n"
            . 'For each item that clearly appears in the bulletin with the same product type and size, return the SRP range across the listed brands. '
            . 'Skip items that are not in the bulletin. Return JSON: {"items": {"<key>": {"min": number, "max": number, "matched": "<bulletin products used>"}}}'
        );

        $matched = $this->collect($answer['data']['items'] ?? [], $goods, $found, 'DTI SRP bulletin', $pdfUrl);

        return ['status' => $answer ? 'ok' : 'no AI provider answered', 'url' => $pdfUrl, 'matched' => $matched, 'provider' => $answer['provider'] ?? null];
    }

    private function fromTgp(array $medicine, array &$found): array
    {
        if ($medicine === []) {
            return ['status' => 'skipped'];
        }

        $candidates = [];
        foreach ($medicine as $key => $item) {
            $query = $this->searchTerms($item['name']);
            try {
                $html = Http::timeout(30)->withUserAgent(config('pricing.user_agent'))
                    ->get(sprintf(config('pricing.tgp_search'), urlencode($query)))->body();
                $products = $this->parseStoreResults($html, explode(' ', $query)[0]);
                if ($products) {
                    $candidates[$key] = ['item' => "{$item['name']} — {$item['size']}", 'products' => array_slice($products, 0, 8)];
                }
            } catch (\Throwable $exception) {
                Log::warning('TGP search failed', ['query' => $query, 'error' => $exception->getMessage()]);
            }
            sleep((int) config('pricing.request_delay', 1));
        }

        if ($candidates === []) {
            return ['status' => 'failed', 'message' => 'No store results could be read'];
        }

        $answer = $this->ai->json(
            'You match Philippine pharmacy store listings (TGP) to a price list and convert pack prices to the unit the price list uses (e.g. a 100pcs box at ₱250 is ₱2.50 per tablet).',
            'Candidates per price list item (JSON): ' . json_encode($candidates, JSON_UNESCAPED_UNICODE) . "\n\n"
            . 'For each item, use only products with the same medicine and strength. Convert to the price list unit. '
            . 'Return JSON: {"items": {"<key>": {"min": <lowest unit price>, "max": <highest unit price>, "matched": "<products used>"}}}; omit items with no real match.'
        );

        $matched = $this->collect($answer['data']['items'] ?? [], $medicine, $found, 'TGP online store', 'https://tgp.com.ph');

        return ['status' => $answer ? 'ok' : 'no AI provider answered', 'searched' => count($medicine), 'with_results' => count($candidates), 'matched' => $matched, 'provider' => $answer['provider'] ?? null];
    }

    private function fromWebSearch(array $remaining, array &$found): array
    {
        if ($remaining === []) {
            return ['status' => 'skipped'];
        }

        $matched = 0;
        $used = false;
        foreach (array_chunk($remaining, 12, true) as $chunk) {
            $list = collect($chunk)->map(fn (array $item): string => "{$item['key']}: {$item['name']} — {$item['size']}")->implode("\n");
            $answer = $this->ai->searchJson(
                'You find current retail prices in the Philippines for a charity foundation\'s budgeting. Prefer DTI SRP, TGP and major retailers (Puregold, SM Markets, Mercury Drug).',
                "Items (key: name — size):\n{$list}\n\nReturn JSON: {\"items\": {\"<key>\": {\"min\": number, \"max\": number, \"matched\": \"<product and store>\"}}}; omit items you cannot price confidently."
            );
            if ($answer === null) {
                break;
            }
            $used = true;
            $matched += $this->collect($answer['data']['items'] ?? [], $chunk, $found, 'Web search (Gemini · Google Search)', $answer['sources'][0] ?? null);
        }

        return ['status' => $used ? 'ok' : 'unavailable (Gemini web search did not answer: check GEMINI_API_KEY and AI_GEMINI_SEARCH_MODEL, or see the log)', 'searched' => count($remaining), 'matched' => $matched];
    }

    /**
     * Keep valid AI answers for known keys. The budgeting price is the top of the range found.
     */
    private function collect(array $answers, array $items, array &$found, string $source, ?string $url): int
    {
        $count = 0;
        foreach ($answers as $key => $answer) {
            if (! isset($items[$key]) || ! is_array($answer) || ! is_numeric($answer['max'] ?? null)) {
                continue;
            }
            $min = (float) ($answer['min'] ?? $answer['max']);
            $max = (float) $answer['max'];
            [$min, $max] = [min($min, $max), max($min, $max)];
            $found[$key] = ['price' => $max, 'min' => $min, 'max' => $max, 'matched' => (string) ($answer['matched'] ?? ''), 'source' => $source, 'url' => $url];
            $count++;
        }

        return $count;
    }

    /**
     * "Amoxicillin 500 mg capsule" → "amoxicillin 500mg".
     */
    private function searchTerms(string $name): string
    {
        $name = strtolower(preg_replace('/\(.*?\)/', '', $name));
        $name = preg_replace('/(\d+)\s*(mg|ml|g)\b/', '$1$2', $name);
        $words = array_values(array_filter(preg_split('/[\s,\/]+/', $name), fn (string $word): bool => ! in_array($word, ['generic', 'branded', 'per', 'tablet', 'capsule', 'syrup'], true)));

        return implode(' ', array_slice($words, 0, 2));
    }

    /**
     * Product title + price from a WooCommerce search results page, keeping titles that mention the keyword.
     */
    private function parseStoreResults(string $html, string $keyword): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new \DOMXPath($dom);
        $products = [];

        foreach ($xpath->query("//li[contains(concat(' ', normalize-space(@class), ' '), ' product ')]") as $node) {
            $title = trim($xpath->evaluate("string(.//*[contains(@class, 'woocommerce-loop-product__title')])", $node));
            $prices = $xpath->query(".//*[contains(@class, 'woocommerce-Price-amount')]", $node);
            if ($title === '' || $prices->length === 0 || stripos($title, $keyword) === false) {
                continue;
            }
            // The last amount is the current (sale) price.
            $price = (float) preg_replace('/[^0-9.]/', '', $prices->item($prices->length - 1)->textContent);
            if ($price > 0) {
                $products[$title] = ['title' => html_entity_decode($title), 'price' => $price];
            }
        }

        return array_values($products);
    }
}
