<?php

declare(strict_types=1);

/*
 * 04 — Every overflow mode (what happens when the limit is hit)
 *
 *     php examples/php/04_overflow_modes.php
 */

require __DIR__.'/../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\Exceptions\RateLimitExceededException;
use ZeroxBliv\CallThrottle\Store\ArrayStore;
use ZeroxBliv\CallThrottle\Throttle;

$store = new ArrayStore();

// Helper: a fresh 2/second throttle on its own id, with its burst already used up.
$exhausted = function (string $id, callable $configure = null) use ($store): Throttle {
    $builder = Throttle::for($id)->allow(2)->per('second')->store($store);
    if ($configure) {
        $configure($builder);
    }
    $throttle = $builder->build();
    $throttle->run(fn () => null); // burst call 1
    $throttle->run(fn () => null); // burst call 2 (burst now used up)

    return $throttle;
};

// --- Mode 1: default — block and wait, then run ---
$t = $exhausted('mode-block');
$start = microtime(true);
$t->run(fn () => null); // waits ~0.5s (one emission interval for 2/sec)
printf("1) block-and-wait: the 3rd call waited %.3fs, then ran\n", microtime(true) - $start);

// --- Mode 2: bounded wait — throws if the wait exceeds maxWait ---
$t = $exhausted('mode-maxwait', fn ($b) => $b->maxWait(0.05));
try {
    $t->run(fn () => null);
} catch (RateLimitExceededException $e) {
    printf("2) maxWait(0.05): threw for '%s', would need %.3fs\n", $e->limiterId, $e->waitSeconds);
}

// --- Mode 3: throwOnLimit — never waits, throws immediately ---
$t = $exhausted('mode-throw', fn ($b) => $b->throwOnLimit());
try {
    $t->run(fn () => null);
} catch (RateLimitExceededException $e) {
    printf("3) throwOnLimit(): threw immediately (retry after %.3fs)\n", $e->waitSeconds);
}

// --- Mode 4: attempt — non-blocking, returns null and does NOT run the callback ---
$t = $exhausted('mode-attempt');
$ran = false;
$result = $t->attempt(function () use (&$ran) {
    $ran = true;

    return 'value';
});
printf("4) attempt(): returned %s, callback ran = %s\n", var_export($result, true), var_export($ran, true));
