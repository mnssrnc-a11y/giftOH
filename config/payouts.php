<?php

/*
|--------------------------------------------------------------------------
| Automatic transfers (bank-to-bank / e-wallet payouts)
|--------------------------------------------------------------------------
| When a request is approved, its list of people is in, and the donations on hand cover it in the
| release line, the super admin can send the money to the receiver's account, confirmed with a
| code emailed to them.
|
| PAYOUT_DRIVER:
|   "manual"  (default) an admin sends the transfer from the foundation's bank and records it with
|             the proof.
|   "sandbox" a built-in practice bank: the whole transfer flow works (send, confirm, status,
|             release recorded) but NO real money moves. For demos and testing.
|   "xendit"  Xendit's Payouts API: InstaPay / PESONet to Philippine banks, and e-wallets. A
|             test key (xnd_development_...) simulates transfers; a live key
|             (xnd_production_...) sends real money from the Xendit balance. Needs a secret key
|             with "Money-out" write permission and the webhook token. See DEPLOY.md →
|             "Automatic transfers".
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

    'sandbox' => [
        // Money in the practice payout account (it goes down with each practice transfer).
        'balance' => (float) env('PAYOUT_SANDBOX_BALANCE', 100000),
        // A practice transfer is confirmed this many seconds after it is sent.
        'settle_seconds' => (int) env('PAYOUT_SANDBOX_SETTLE_SECONDS', 20),
        // Account numbers that fail, like Xendit's test mode: number => failure code.
        'failing_accounts' => [
            '999999' => 'REJECTED_BY_CHANNEL',
            '121212' => 'INVALID_DESTINATION',
            '123456' => 'TEMPORARY_TRANSFER_ERROR',
        ],
    ],

    /*
     | Xendit channel code for each bank / e-wallet the requester can choose, matched by name to the
     | channel list Xendit returns for the account (GET /payouts_channels, October 2026). The super
     | admin can change any of them in System settings → Bank transfers. Not on Xendit: Overseas
     | Filipino Bank (OFBank), Netbank and PayPal; those grants are sent by hand.
     */
    'channels' => [
        'BDO Unibank' => 'PH_BDO',
        'Land Bank of the Philippines' => 'PH_LBP',
        'Bank of the Philippine Islands (BPI)' => 'PH_BPI',
        'Metrobank' => 'PH_MET',
        'Philippine National Bank (PNB)' => 'PH_PNB',
        'Security Bank' => 'PH_SEC',
        'China Banking Corporation (China Bank)' => 'PH_CBC',
        'RCBC' => 'PH_RCBC',
        'UnionBank of the Philippines' => 'PH_UBP',
        'EastWest Bank' => 'PH_EWB',
        'Development Bank of the Philippines (DBP)' => 'PH_DBP',
        'PSBank' => 'PH_PSB',
        'Asia United Bank (AUB)' => 'PH_AUB',
        'Maybank Philippines' => 'PH_MPI',
        'Philippine Bank of Communications (PBCOM)' => 'PH_PBC',
        'Bank of Commerce' => 'PH_BOC',
        'Philippine Veterans Bank' => 'PH_PVB',
        'CTBC Bank Philippines' => 'PH_CTBC',
        'Sterling Bank of Asia' => 'PH_SBA',
        'CIMB Bank Philippines' => 'PH_CIMB',
        'SeaBank Philippines' => 'PH_SEA',
        'GoTyme Bank' => 'PH_GOTYME',
        'Tonik Digital Bank' => 'PH_TONIK',
        'Maya Bank' => 'PH_MAYA',
        'UNOBank' => 'PH_UNO',
        'GCash' => 'PH_GCASH',
        'Maya' => 'PH_PAYMAYA',
    ],
];
