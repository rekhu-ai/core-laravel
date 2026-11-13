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
    // seconds to wait before each new try when Rekhu cannot be reached (2, 4 and 8: about 14 seconds in all); fewer or
    // shorter for a screen a user waits on, for example "1" (one try more after a second) or "" (no try more)
    'retry_waits' => env('REKHU_RETRY_WAITS', '2,4,8'),

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

    // The file check on your own site (@rekhu/seshat-widget): routes that check, fix, preview and download a print file
    // for the signed-in user (see PROTOCOL.md in the widget). Off until you turn it on, or call Rekhu::seshatRoutes()
    // in your own route file.
    'seshat' => [
        'routes' => (bool) env('REKHU_SESHAT_ROUTES', false),
        'path' => env('REKHU_SESHAT_PATH', 'rekhu/seshat'),
        // who may check a file: Blade, Livewire, Inertia: web,auth. An API for Nuxt, Next or mobile: api,auth:sanctum
        'middleware' => explode(',', env('REKHU_SESHAT_MIDDLEWARE', 'web,auth')),
        'max_mb' => (int) env('REKHU_SESHAT_MAX_MB', 200),        // the largest file taken; over 20 MB goes on to Rekhu in parts
        'explain' => (bool) env('REKHU_SESHAT_EXPLAIN', true),    // an explanation for the person who sent the file
        // your defaults for what the page does not send; null: Seshat's own, from the print standards
        // (bleed 3 mm, safe zone 3 mm, 250 ppi, 320 % total ink, CMYK)
        'specs' => [
            'bleed_mm' => null,
            'safe_mm' => null,
            'min_ppi' => null,
            'max_ink' => null,
            'colour' => null,
            'profile' => null,
        ],
        // the fixes the page may ask for, by name (add_bleed, convert_cmyk …); null: every fix Seshat decides on
        'fixes' => null,
        // the page may name a recipe kept in your Rekhu account (its specs and fixes per product)
        'recipes' => true,
    ],

    // Every call to a knower is kept in your own table (who, which knower, what Rekhu charged, your own price), and
    // App\Events listeners get Rekhu\Core\Events\KnowerUsed. Turn it off only when you keep usage elsewhere.
    'ledger' => [
        'enabled' => (bool) env('REKHU_LEDGER', true),
        'table' => 'rekhu_usage',
        'connection' => env('REKHU_LEDGER_CONNECTION'),
    ],
];
