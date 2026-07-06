<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle;

/**
 * The Generic Cell Rate Algorithm (GCRA), a smooth leaky-rate limiter.
 *
 * The entire state for a limiter is a single float: the theoretical arrival
 * time (TAT). Given the stored TAT and the current time, this computes whether
 * a call may proceed, how long it must wait, and the new TAT to persist.
 *
 * With emission interval T = period / count and burst tolerance equal to the
 * period, a limit of `count` per `period` allows an initial burst of exactly
 * `count` calls, then paces one call every T seconds.
 */
final class Gcra
{
    /**
     * @param  float|null  $tat  the stored theoretical arrival time, or null if none
     * @return array{0: bool, 1: float, 2: float|null}  [allowed, waitSeconds, newTat|null]
     *         newTat is null when the call is refused (state must not change).
     */
    public static function reserve(?float $tat, float $now, RateLimit $limit, float $maxWaitSeconds): array
    {
        $emissionInterval = $limit->emissionInterval();
        $burstTolerance = $limit->periodSeconds;

        $tat ??= $now;

        $newTat = max($tat, $now) + $emissionInterval;
        $wait = ($newTat - $burstTolerance) - $now;

        if ($wait <= $maxWaitSeconds) {
            return [true, max(0.0, $wait), $newTat];
        }

        return [false, max(0.0, $wait), null];
    }
}
