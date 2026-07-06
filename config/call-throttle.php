<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default coordination driver
    |--------------------------------------------------------------------------
    |
    | Which backend coordinates the rate across processes: "file", "redis" or
    | "database". "file" works with zero setup; "redis" and "database" reuse
    | your application's existing connections (see below).
    |
    */

    'default' => env('CALL_THROTTLE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Stores
    |--------------------------------------------------------------------------
    */

    'stores' => [

        'file' => [
            'driver' => 'file',
            // Private, gitignored, auto-created on first use.
            'path' => storage_path('framework/call-throttle'),
        ],

        'redis' => [
            'driver' => 'redis',
            // A connection name from config/database.php ("redis") — REDIS_* env.
            'connection' => env('CALL_THROTTLE_REDIS_CONNECTION', 'default'),
        ],

        'database' => [
            'driver' => 'database',
            // null uses the app's default DB connection (DB_* env).
            'connection' => env('CALL_THROTTLE_DB_CONNECTION'),
            'state_table' => 'call_throttle_limiter_state',
            'config_table' => 'call_throttle_limiters',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Registered limiters
    |--------------------------------------------------------------------------
    |
    | Define each shared limiter's rate here, once. Every worker registers these
    | at boot against the default store, so the whole fleet agrees on the rate no
    | matter which process starts first. Reference them by name in your code:
    |
    |     CallThrottle::limiter('external-api')->run(fn () => Http::get(...));
    |
    | Each entry is a "count/period" string, or an array for extra options:
    |
    |     'external-api' => '100/minute',
    |     'reports'      => ['rate' => '30/minute', 'max_wait' => 10],
    |     'webhooks'     => ['allow' => 5, 'per' => 'second', 'throw' => true],
    |
    */

    'limiters' => [
        //
    ],

];
