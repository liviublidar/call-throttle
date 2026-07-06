<?php

declare(strict_types=1);

/*
 * Example config/call-throttle.php after `php artisan vendor:publish --tag=call-throttle-config`.
 *
 * Shows every way to select a driver and define shared limiters. The service
 * provider will register each limiter on the default store at boot.
 */

return [

    // Which backend coordinates the rate across processes.
    // file  = no setup (private storage/framework/call-throttle)
    // redis = reuse config/database.php redis connection (REDIS_* env), same as the larvel drivers use everywere
    // database = reuse your DB connection (DB_* env) + the published migration, same as the laravel drivers
    'default' => env('CALL_THROTTLE_DRIVER', 'redis'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/call-throttle'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('CALL_THROTTLE_REDIS_CONNECTION', 'default'),
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('CALL_THROTTLE_DB_CONNECTION'),
            'state_table' => 'call_throttle_limiter_state',
            'config_table' => 'call_throttle_limiters',
        ],
    ],

    // Period vocabulary (case-insensitive) for the "count/period" string and the
    // 'per' key:
    //   second : 'second' | 'sec' | 's'   (1s)
    //   minute : 'minute' | 'min' | 'm'   (60s)
    //   hour   : 'hour'   | 'h'           (3600s)
    //   day    : 'day'    | 'd'           (86400s)
    //   raw    : any number = seconds     (e.g. '100/60', 'per' => 2)
    // No 'week'/'month' keyword — use raw seconds. Keywords are singular.
    'limiters' => [

        // 1) Shorthand string "count/period" — one per period keyword:
        'search-api' => '4/second',
        'external-api' => '100/minute',
        'reports-api' => '1000/hour',
        'exports' => '50000/day',

        // ...aliases work too: '4/s', '100/min', '1000/h', '50000/d'
        // ...raw seconds work too: '100/60' (== per minute), '1/2.5'

        // 2) Array with a rate string + a default wait policy:
        'reports' => [
            'rate' => '30/minute',
            'max_wait' => 10, // run() blocks up to 10s for a slot before throwing
        ],

        // 3) Array with allow/per + fail-fast policy (per takes the same vocabulary):
        'webhooks' => [
            'allow' => 5,
            'per' => 'second',
            'throw' => true, // run() throws immediately instead of waiting
        ],

        // 4) Period as a number of seconds:
        'billing-sync' => ['allow' => 1, 'per' => 2], // 1 call every 2 seconds
    ],

];
