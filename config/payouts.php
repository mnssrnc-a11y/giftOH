<?php

/*
|--------------------------------------------------------------------------
| Automatic transfers (bank-to-bank / e-wallet payouts)
|--------------------------------------------------------------------------
| When a request is approved, its list of people is in, and the foundation's funds cover the
| amount, the super admin can send the money to the receiver's account with one confirmed click.
|
| "manual" (default): the admin sends the transfer from the foundation's bank and records it with
| the proof, as before. "xendit": the money is sent through Xendit's Payouts API (InstaPay or
| PESONet to banks, and to GCash/Maya). It needs a verified Xendit business account with a funded
| balance, a secret API key with "Money-out" write permission, and the webhook token. See
| DEPLOY.md → "Automatic transfers".
*/

return [

    'driver' => env('PAYOUT_DRIVER', 'manual'),

    'xendit' => [
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
        'secret_key' => env('XENDIT_SECRET_KEY'),
        // Xendit sends this token in the "x-callback-token" header of every webhook.
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'timeout' => (int) env('XENDIT_TIMEOUT', 30),
    ],

    /*
     | Xendit channel code for each bank / e-wallet the requester can choose. Codes confirmed in
     | Xendit's Philippines payout docs are filled in; copy the rest from the Xendit dashboard
     | (Payouts → channel list) when the account is ready. A provider without a code is sent by the
     | manual process. PayPal is not a Xendit channel.
     */
    'channels' => [
        'BDO Unibank' => 'PH_BDO',
        'Bank of the Philippine Islands (BPI)' => 'PH_BPI',
        'Security Bank' => 'PH_SEC',
        'UnionBank of the Philippines' => 'PH_UBP',
        'GCash' => 'PH_GCASH',
        // 'Land Bank of the Philippines' => '',
        // 'Metrobank' => '',
        // 'Maya' => '',
        // 'SeaBank Philippines' => '',
    ],
];
