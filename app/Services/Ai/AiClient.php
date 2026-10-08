<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One entry point for every AI call. Tries the configured free-tier providers in turn and
 * skips any that is cooling down after a rate limit, quota, auth or server error, so a single
 * provider's daily limit never stops the AI features. See config/ai.php.
 */
class AiClient
{
    /**
     * Ask for a JSON object. Returns ['data' => array, 'provider' => string, 'model' => string]
     * or null when no provider could answer.
     */
    public function json(string $system, string $prompt): ?array
    {
        $cacheKey = 'ai:answer:' . sha1($system . "\n" . $prompt);
        $ttl = (int) config('ai.cache_seconds', 0);
        if ($ttl > 0 && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        foreach ($this->providerOrder() as $name) {
            $provider = $this->provider($name);
            if ($provider === null || $this->unavailableReason($name, $provider) !== null) {
                continue;
            }

            $text = $this->call($name, $provider, $system, $prompt);
            if ($text === null) {
                continue;
            }

            $data = $this->decodeJson($text);
            if ($data === null) {
                Log::warning("AI provider {$name} returned text that is not JSON; trying the next provider.");
                continue;
            }

            Cache::add($this->usageKey($name), 0, now()->endOfDay());
            Cache::increment($this->usageKey($name));
            $answer = ['data' => $data, 'provider' => $name, 'model' => $provider['model']];
            if ($ttl > 0) {
                Cache::put($cacheKey, $answer, $ttl);
            }

            return $answer;
        }

        Log::warning('No AI provider could answer; the caller falls back to rule-based logic.');

        return null;
    }

    /**
     * Status of every provider for the admin screen and `php artisan ai:status`.
     */
    public function status(): array
    {
        $rows = [];
        foreach (array_keys(config('ai.providers', [])) as $name) {
            $provider = $this->provider($name);
            $rows[] = [
                'name' => $name,
                'model' => config("ai.providers.{$name}.model"),
                'configured' => $provider !== null,
                'in_order' => in_array($name, config('ai.order', []), true),
                'used_today' => (int) Cache::get($this->usageKey($name), 0),
                'daily_limit' => (int) config("ai.providers.{$name}.daily_limit", 0),
                'unavailable' => $provider ? $this->unavailableReason($name, $provider) : 'No API key set',
                'last_error' => Cache::get("ai:last_error:{$name}"),
            ];
        }

        return $rows;
    }

    /**
     * Ask Gemini with Google Search grounding (free tier on the Flash models). Returns
     * ['data' => array, 'sources' => [url, ...], 'model' => string] or null when unavailable.
     */
    public function searchJson(string $system, string $prompt): ?array
    {
        $gemini = $this->provider('gemini');
        $model = config('ai.search.model');
        $usageKey = 'ai:usage:gemini-search:' . now()->format('Y-m-d');
        if ($gemini === null || ! $model || Cache::get('ai:cooldown:gemini-search')
            || (int) Cache::get($usageKey, 0) >= (int) config('ai.search.daily_limit', 400)) {
            return null;
        }

        try {
            $response = Http::timeout(max(45, (int) config('ai.timeout', 25)))
                ->withHeaders(['x-goog-api-key' => $gemini['api_key']])
                ->post(rtrim($gemini['base_url'], '/') . "/models/{$model}:generateContent", [
                    'system_instruction' => ['parts' => [['text' => $system . "\nAnswer with a single JSON object only."]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => ['temperature' => 0.1],
                ]);
        } catch (ConnectionException $exception) {
            $this->coolDown('gemini-search', 60, $exception->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->coolDown('gemini-search', $response->status() === 429 ? 300 : 120, "HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300));

            return null;
        }

        Cache::add($usageKey, 0, now()->endOfDay());
        Cache::increment($usageKey);

        $text = collect((array) $response->json('candidates.0.content.parts'))->pluck('text')->filter()->implode("\n");
        $data = $this->decodeJson($text);
        $sources = collect((array) $response->json('candidates.0.groundingMetadata.groundingChunks'))
            ->pluck('web.uri')->filter()->unique()->values()->all();

        return $data === null ? null : ['data' => $data, 'sources' => $sources, 'model' => $model];
    }

    /**
     * Send a tiny request to one provider to check its key and model.
     *
     * @return array{ok: bool, message: string, ms: int}
     */
    public function ping(string $name): array
    {
        $provider = $this->provider($name);
        if ($provider === null) {
            return ['ok' => false, 'message' => 'Not configured (no API key or model)', 'ms' => 0];
        }

        $started = microtime(true);
        $text = $this->call($name, $provider, 'You are a health check.', 'Reply with the JSON object {"ok": true}.');
        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($text !== null && $this->decodeJson($text) !== null) {
            Cache::forget("ai:cooldown:{$name}");

            return ['ok' => true, 'message' => "{$provider['model']} answered", 'ms' => $ms];
        }

        return ['ok' => false, 'message' => Cache::get("ai:last_error:{$name}") ?? 'Answer was not valid JSON', 'ms' => $ms];
    }

    public function hasProvider(): bool
    {
        foreach ($this->providerOrder() as $name) {
            if ($this->provider($name) !== null) {
                return true;
            }
        }

        return false;
    }

    private function providerOrder(): array
    {
        $order = array_values(array_filter(
            config('ai.order', []),
            fn (string $name): bool => $this->provider($name) !== null
        ));

        if (config('ai.strategy') === 'round_robin' && count($order) > 1) {
            $start = Cache::increment('ai:round_robin') % count($order);
            $order = array_merge(array_slice($order, $start), array_slice($order, 0, $start));
        }

        return $order;
    }

    private function provider(string $name): ?array
    {
        $provider = config("ai.providers.{$name}");
        if (! is_array($provider)) {
            return null;
        }

        // Every provider works through an API key; one without a key is never called.
        return filled($provider['api_key'] ?? null) && filled($provider['model'] ?? null) ? $provider : null;
    }

    private function unavailableReason(string $name, array $provider): ?string
    {
        if ($until = Cache::get("ai:cooldown:{$name}")) {
            return 'Cooling down until ' . date('H:i:s', (int) $until);
        }

        $limit = (int) ($provider['daily_limit'] ?? 0);
        if ($limit > 0 && (int) Cache::get($this->usageKey($name), 0) >= $limit) {
            return "Daily limit of {$limit} reached";
        }

        return null;
    }

    private function call(string $name, array $provider, string $system, string $prompt): ?string
    {
        try {
            $response = $provider['driver'] === 'gemini'
                ? $this->callGemini($provider, $system, $prompt)
                : $this->callOpenAiCompatible($provider, $system, $prompt);
        } catch (ConnectionException $exception) {
            $this->coolDown($name, (int) config('ai.cooldown.server_error', 60), 'Connection failed: ' . $exception->getMessage());

            return null;
        }

        if ($response->successful()) {
            $text = $provider['driver'] === 'gemini'
                ? $response->json('candidates.0.content.parts.0.text')
                : $response->json('choices.0.message.content');

            return is_string($text) && trim($text) !== '' ? $text : null;
        }

        $status = $response->status();
        $message = "HTTP {$status}: " . mb_substr((string) $response->body(), 0, 300);

        if ($status === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            $this->coolDown($name, $retryAfter > 0 ? $retryAfter : (int) config('ai.cooldown.rate_limited', 120), $message);
        } elseif (in_array($status, [401, 403], true)) {
            $this->coolDown($name, (int) config('ai.cooldown.auth_error', 3600), $message);
        } elseif ($status >= 500) {
            $this->coolDown($name, (int) config('ai.cooldown.server_error', 60), $message);
        } else {
            // 400/404 usually mean a retired model name; skip this provider for a while too.
            $this->coolDown($name, (int) config('ai.cooldown.server_error', 60), $message);
        }

        return null;
    }

    private function callGemini(array $provider, string $system, string $prompt): Response
    {
        $url = rtrim($provider['base_url'], '/') . '/models/' . $provider['model'] . ':generateContent';

        return Http::timeout((int) config('ai.timeout', 25))
            ->withHeaders(['x-goog-api-key' => $provider['api_key']])
            ->post($url, [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2],
            ]);
    }

    private function callOpenAiCompatible(array $provider, string $system, string $prompt): Response
    {
        $body = [
            'model' => $provider['model'],
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $system . "\nRespond with a single JSON object only."],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        if ($provider['json_mode'] ?? false) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $request = Http::timeout((int) config('ai.timeout', 25))->withHeaders($provider['headers'] ?? []);
        if (filled($provider['api_key'] ?? null)) {
            $request = $request->withToken($provider['api_key']);
        }

        return $request->post(rtrim($provider['base_url'], '/') . '/chat/completions', $body);
    }

    /**
     * Accept plain JSON, JSON wrapped in ``` fences, or JSON surrounded by prose.
     */
    private function decodeJson(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            $decoded = $start !== false && $end > $start ? json_decode(substr($text, $start, $end - $start + 1), true) : null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function coolDown(string $name, int $seconds, string $reason): void
    {
        $until = now()->addSeconds(max(1, $seconds));
        Cache::put("ai:cooldown:{$name}", $until->timestamp, $until);
        Cache::put("ai:last_error:{$name}", now()->format('M d H:i') . ' — ' . $reason, now()->addDay());
        Log::warning("AI provider {$name} skipped for {$seconds}s.", ['reason' => $reason]);
    }

    private function usageKey(string $name): string
    {
        return "ai:usage:{$name}:" . now()->format('Y-m-d');
    }
}
