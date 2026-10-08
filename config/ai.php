<?php

/*
|--------------------------------------------------------------------------
| AI providers
|--------------------------------------------------------------------------
| Every AI feature goes through App\Services\Ai\AiClient, which tries these providers in order
| and skips any that is rate-limited, out of quota, misconfigured or down. Spreading calls over
| several free tiers avoids the daily token/request limits of any single one.
|
| Only providers that work through an API key are supported, and a provider is used only when
| its key is set. Free tiers (no credit card) as of 2026 — sign up, create a key, paste it into .env:
|   Gemini      https://aistudio.google.com/apikey
|   Groq        https://console.groq.com/keys
|   OpenRouter  https://openrouter.ai/keys          (free models end in ":free", ~50 requests/day)
| Model names change over time; override them with the *_MODEL variables.
*/

return [

    // "failover": always start with the first provider. "round_robin": rotate the starting
    // provider on each call so usage is spread evenly across all free tiers.
    'strategy' => env('AI_STRATEGY', 'failover'),

    'timeout' => (int) env('AI_TIMEOUT', 25),

    // Identical prompts within this many seconds reuse the previous answer (0 disables).
    'cache_seconds' => (int) env('AI_CACHE_SECONDS', 3600),

    // How long to skip a provider after a failure.
    'cooldown' => [
        'rate_limited' => 120,   // HTTP 429 when no Retry-After header is sent
        'server_error' => 60,    // 5xx, timeouts, connection errors
        'auth_error' => 3600,    // 401/403: bad or revoked key
    ],
    // A provider that reaches its "daily_limit" (requests counted locally) is skipped until midnight.

    'providers' => [
        'gemini' => [
            'driver' => 'gemini',
            'base_url' => env('AI_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('AI_GEMINI_MODEL', 'gemini-flash-latest'),
            'daily_limit' => (int) env('AI_GEMINI_DAILY_LIMIT', 0),
        ],
        'groq' => [
            'driver' => 'openai',
            'base_url' => env('AI_GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('AI_GROQ_MODEL', 'openai/gpt-oss-120b'),
            'json_mode' => true,
            'daily_limit' => (int) env('AI_GROQ_DAILY_LIMIT', 900),
        ],
        'openrouter' => [
            'driver' => 'openai',
            'base_url' => env('AI_OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('AI_OPENROUTER_MODEL', 'nvidia/nemotron-3-super-120b-a12b:free'),
            'json_mode' => false,
            'daily_limit' => (int) env('AI_OPENROUTER_DAILY_LIMIT', 45),
            'headers' => ['HTTP-Referer' => env('APP_URL', 'http://localhost'), 'X-Title' => env('APP_NAME', 'Gift of Hope')],
        ],
    ],

    // Web search for the monthly price update (prices of items the DTI/TGP pages don't list, and
    // new items admins add). Uses Gemini's free "Grounding with Google Search" on the current
    // Flash model (the -latest alias follows Google's model updates). Needs GEMINI_API_KEY.
    'search' => [
        'model' => env('AI_GEMINI_SEARCH_MODEL', 'gemini-flash-latest'),
        'daily_limit' => (int) env('AI_SEARCH_DAILY_LIMIT', 400),
    ],

    // Order to try providers in. Remove or reorder names to taste.
    'order' => array_filter(array_map('trim', explode(',', env('AI_PROVIDER_ORDER', 'gemini,groq,openrouter')))),
];
