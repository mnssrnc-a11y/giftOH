<?php

/*
|--------------------------------------------------------------------------
| Monthly AI price update (php artisan prices:update, scheduled monthly)
|--------------------------------------------------------------------------
| Goods: DTI "Latest SRPs of Basic Necessities and Prime Commodities" — the newest SRP bulletin
|        (PDF) linked from the page is read and the AI matches its items to the price list.
| Medicine: TGP (The Generics Pharmacy) online store — the store search results are read and the
|        AI converts pack prices to the unit in the price list (e.g. per tablet).
| Anything still unmatched: Gemini web search (Google Search grounding, free tier).
*/

return [
    'dti_page' => env('PRICING_DTI_PAGE', 'https://www.dti.gov.ph/dti-consumer-space/dti-latest-srps-basic-necessities-prime-commodities'),
    'tgp_search' => env('PRICING_TGP_SEARCH', 'https://tgp.com.ph/?s=%s&post_type=product'),
    'user_agent' => 'GiftOfHope-PriceUpdater/1.0 (+' . env('APP_URL', 'http://localhost') . ')',

    // Which price list groups (price_list/{group} in Firebase) each source covers; anything the
    // source does not list goes to web search.
    'tgp_groups' => ['medical'],
    'dti_groups' => ['food', 'cleaning'],

    // A new price outside this ratio of the current price is treated as a probable mismatch
    // and left for review instead of being applied.
    'max_change_ratio' => 4.0,

    // Seconds to wait between store search requests (be polite to the source sites).
    'request_delay' => 1,
];
