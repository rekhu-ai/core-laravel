<?php

// Rekhu for Laravel. Everything can be set from .env; `php artisan rekhu:core --install` writes the settings, you add the key.
return [

    // The app key from the Rekhu account (Projects → your app → Connection). One key per environment. An app that
    // already has Rekhu Assistant can use its key.
    'key' => env('REKHU_APP_KEY', env('REKHU_ASSISTANT_KEY')),

    // Where Rekhu runs for this app:
    //   cloud    Rekhu's platform (the default)
    //   private  Rekhu inside your own network: set REKHU_PLATFORM to its address, for example https://rekhu.intranet.example.com
    // The knowers and their answers are the same in every mode.
    'deployment' => env('REKHU_DEPLOYMENT', 'cloud'),
    'platform' => env('REKHU_PLATFORM', 'https://api.rekhu.ai'),
    'timeout' => (int) env('REKHU_TIMEOUT', 180),             // seconds: a big print file takes a while to check and fix

    // single: the app is one customer of yours. multi: every user belongs to one of your customers (a tenant); name it
    // with Rekhu::tenantUsing(fn ($user) => $user->team) in a service provider.
    'tenancy' => env('REKHU_TENANCY', 'single'),

    // The languages your users read; a knower answers in the user's own (Rekhu::localeUsing, else the app's locale).
    'locales' => explode(',', env('REKHU_LOCALES', 'en')),

    // The knowers this app uses (`php artisan rekhu:core --install=machines` adds one) and their limits a day. null: no limit
    // of your own; Rekhu::limitsUsing() can set them per tenant or plan.
    'knowers' => array_values(array_filter(explode(',', env('REKHU_KNOWERS', '')))),
    'limits' => [
        'machines' => ['per_day_per_tenant' => null, 'per_day_per_user' => null],
        'seshat' => ['per_day_per_tenant' => null, 'per_day_per_user' => null],
    ],

    // Every call to a knower is kept in your own table (who, which knower, what Rekhu charged, your own price), and
    // App\Events listeners get Rekhu\Core\Events\KnowerUsed. Turn it off only when you keep usage elsewhere.
    'ledger' => [
        'enabled' => (bool) env('REKHU_LEDGER', true),
        'table' => 'rekhu_usage',
        'connection' => env('REKHU_LEDGER_CONNECTION'),
    ],
];
