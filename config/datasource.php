<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data Source Driver Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines which data source the application uses.
    | Currently, the application is transitioning to Firebase Realtime Database.
    |
    | Supported drivers: 'firebase'
    |
    */

    'driver' => env('DATA_DRIVER', 'firebase'),

    /*
    |--------------------------------------------------------------------------
    | Enable Firebase
    |--------------------------------------------------------------------------
    |
    | Set this to true to use Firebase repositories for all data operations.
    | When false, the application falls back to SQL/Eloquent models.
    |
    */

    'use_firebase' => env('USE_FIREBASE', true),

    /*
    |--------------------------------------------------------------------------
    | Migration Status
    |--------------------------------------------------------------------------
    |
    | Track the status of the Firebase migration.
    | This helps identify which features are Firebase-ready.
    |
    */

    'migration_status' => [
        'users' => true,              // Firebase migration complete
        'funding_requests' => true,   // Firebase migration complete
        'donations' => true,          // Firebase migration complete
        'verifications' => true,      // Firebase migration complete
        'password_resets' => true,    // Firebase migration complete
    ],
];
