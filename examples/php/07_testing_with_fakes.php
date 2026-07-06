<?php

declare(strict_types=1);

/*
 * 07 — Deterministic testing with injected Clock and Sleeper
 *
 * The builder's clock() and sleeper() let you drive time yourself, so pacing is
 * testable without real waits. Here a fake sleeper records the requested sleep
 * and advances the fake clock instead of actually sleeping.
 *
 *     php examples/php/07_testing_with_fakes.php
 */

require __DIR__.'/../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\Contracts\Clock;
use ZeroxBliv\CallThrottle\Contracts\Sleeper;
use ZeroxBliv\CallThrottle\Store\ArrayStore;
use ZeroxBliv\CallThrottle\Throttle;

$clock = new class implements Clock {
    public float $now = 1000.0;

    public function now(): float
    {
        return $this->now;
    }
};

$sleeper = new class($clock) implements Sleeper {
    /** @var array<int, float> */
    public array $slept = [];

    public function __construct(private object $clock)
    {
    }

    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->slept[] = $seconds;
        $this->clock->now += $seconds; // no real sleeping — just move time forward
    }
};

$throttle = Throttle::for('under-test')
    ->allow(4)->per('second')
    ->store(new ArrayStore())
    ->clock($clock)      // inject the fake clock
    ->sleeper($sleeper)  // inject the fake sleeper
    ->build();

// First 4 calls fit the burst (no sleep); calls 5 and 6 are paced at 0.25s.
for ($i = 0; $i < 6; $i++) {
    $throttle->run(fn () => null);
}

printf("recorded sleeps (seconds): [%s]\n", implode(', ', array_map(
    static fn ($s) => sprintf('%.2f', $s),
    $sleeper->slept,
)));
printf("fake clock advanced to: %.2f (started at 1000.00)\n", $clock->now);
// Expected: sleeps [0.25, 0.25], clock at 1000.50 — all without real time passing.
