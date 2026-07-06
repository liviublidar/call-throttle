<?php

declare(strict_types=1);

/*
 * 03 — Every way to express a rate
 *
 *     php examples/php/03_rate_definitions.php
 *
 * Accepted period vocabulary (case-insensitive), resolved by
 * RateLimit::periodToSeconds():
 *
 *     second : 'second' | 'sec' | 's'   (1s)
 *     minute : 'minute' | 'min' | 'm'   (60s)
 *     hour   : 'hour'   | 'h'           (3600s)
 *     day    : 'day'    | 'd'           (86400s)
 *     raw    : any number = seconds     (e.g. '100/60', '1/2.5')
 *
 * There is no 'week'/'month' keyword — use raw seconds for those. Keywords are
 * singular ('minute', not 'minutes'); an unknown keyword throws.
 */

require __DIR__.'/../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\ArrayStore;
use ZeroxBliv\CallThrottle\Throttle;

// --- RateLimit factories (used with the registry, or the Throttle constructor) ---
$factories = [
    'perSecond(4)' => RateLimit::perSecond(4),
    'perMinute(100)' => RateLimit::perMinute(100),
    'perHour(1000)' => RateLimit::perHour(1000),
    'of(10, 2.5)' => RateLimit::of(10, 2.5),          // 10 every 2.5 seconds
];

// --- fromString('count/period') — every period keyword, its aliases, and raw seconds ---
$strings = [
    '4/second', '4/sec', '4/s',       // per second
    '100/minute', '100/min', '100/m', // per minute
    '1000/hour', '1000/h',            // per hour
    '50000/day', '50000/d',           // per day
    '100/60',                         // raw seconds (per 60s == per minute)
    '1/2.5',                          // fractional seconds (1 every 2.5s)
];

echo "== factories ==\n";
foreach ($factories as $label => $rate) {
    printRate($label, $rate);
}

echo "\n== fromString ==\n";
foreach ($strings as $spec) {
    printRate("fromString('{$spec}')", RateLimit::fromString($spec));
}

// --- The fluent builder's per() accepts the same keywords/aliases or a number ---
echo "\n== builder ->per(...) ==\n";
$store = new ArrayStore();
foreach (['second', 'sec', 's', 'minute', 'min', 'm', 'hour', 'h', 'day', 'd', 30, 2.5] as $period) {
    Throttle::for('rate-'.$period)->allow(5)->per($period)->store($store)->build();
    printf("per(%s) OK\n", is_string($period) ? "'{$period}'" : $period.'s');
}

// --- equals(): compare two definitions (used internally for conflict detection) ---
echo "\n== equals ==\n";
var_dump(RateLimit::fromString('60/minute')->equals(RateLimit::fromString('60/60'))); // true (same seconds)
var_dump(RateLimit::perSecond(4)->equals(RateLimit::perMinute(4)));                    // false

function printRate(string $label, RateLimit $rate): void
{
    printf("%-26s => %d per %ss  (one call every %.4fs)\n",
        $label, $rate->count, rtrim(rtrim(sprintf('%.3f', $rate->periodSeconds), '0'), '.'), $rate->emissionInterval());
}
