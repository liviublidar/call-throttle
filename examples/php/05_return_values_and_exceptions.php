<?php

declare(strict_types=1);

/*
 * 05 — Return values and exception pass-through
 *
 * The throttle is transparent: run() returns exactly what your callback returns
 * and re-throws exactly what it throws. This is what makes wrapping something
 * like Http::get(...) feel like a direct call.
 *
 *     php examples/php/05_return_values_and_exceptions.php
 */

require __DIR__.'/../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\Store\ArrayStore;
use ZeroxBliv\CallThrottle\Throttle;

$throttle = Throttle::for('passthrough')->allow(10)->per('second')->store(new ArrayStore())->build();

// --- Return values pass straight through (any type) ---
$array = $throttle->run(fn (): array => ['status' => 200, 'body' => 'ok']);
printf("run() returned an array: status=%d body=%s\n", $array['status'], $array['body']);

$object = $throttle->run(fn () => (object) ['id' => 42]);
printf("run() returned an object: id=%d\n", $object->id);

// --- Exceptions from the callback propagate unchanged ---
try {
    $throttle->run(function (): void {
        throw new \RuntimeException('the API call failed');
    });
} catch (\RuntimeException $e) {
    printf("run() re-threw the callback's exception: %s\n", $e->getMessage());
}

// --- attempt() returns the value, or null when throttled (callback not run) ---
$value = $throttle->attempt(fn () => 'ran');
printf("attempt() when a slot is free: %s\n", var_export($value, true));
