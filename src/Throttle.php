<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle;

use ZeroxBliv\CallThrottle\Clock\SystemClock;
use ZeroxBliv\CallThrottle\Contracts\Clock;
use ZeroxBliv\CallThrottle\Contracts\Sleeper;
use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\Exceptions\RateLimitExceededException;
use ZeroxBliv\CallThrottle\Sleeper\UsleepSleeper;

/**
 * An immutable, id-bound rate limiter.
 *
 * Once built, its store, id and limit are fixed for the lifetime of the object;
 * there are no configuration setters, so a live throttler can never be
 * reconfigured. Build one via Throttle::for(...) and reuse it freely.
 */
final readonly class Throttle
{
    public function __construct(
        private Store $store,
        private string $id,
        private RateLimit $limit,
        private float $maxWait = INF,
        private bool $throwOnLimit = false,
        private Clock $clock = new SystemClock(),
        private Sleeper $sleeper = new UsleepSleeper(),
    ) {
    }

    /**
     * Start configuring a throttler for the given limiter id.
     */
    public static function for(string $id): ThrottleBuilder
    {
        return new ThrottleBuilder($id);
    }

    /**
     * A copy with a different max blocking time. Only the wait policy changes —
     * the store, id and rate are preserved, so this cannot cause rate drift.
     */
    public function withMaxWait(float $seconds): self
    {
        return new self($this->store, $this->id, $this->limit, $seconds, false, $this->clock, $this->sleeper);
    }

    /**
     * A copy that throws immediately instead of waiting when no slot is free.
     * Only the wait policy changes; the rate is preserved.
     */
    public function withThrowOnLimit(bool $throw = true): self
    {
        return new self($this->store, $this->id, $this->limit, $this->maxWait, $throw, $this->clock, $this->sleeper);
    }

    /**
     * Reserve a slot (blocking up to maxWait), then run the callback and return
     * its value. Throws RateLimitExceededException when the required wait would
     * exceed maxWait (or immediately, when throwOnLimit is set).
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        $maxWait = $this->throwOnLimit ? 0.0 : $this->maxWait;

        $reservation = $this->store->reserve($this->id, $this->limit, $maxWait, $this->clock->now());

        if (! $reservation->allowed) {
            throw RateLimitExceededException::forLimiter($this->id, $this->limit, $reservation->waitSeconds);
        }

        if ($reservation->waitSeconds > 0) {
            $this->sleeper->sleep($reservation->waitSeconds);
        }

        return $callback();
    }

    /**
     * Non-blocking: run and return the callback's value if a slot is free right
     * now, otherwise return null without invoking the callback and without waiting.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T|null
     */
    public function attempt(callable $callback): mixed
    {
        $reservation = $this->store->reserve($this->id, $this->limit, 0.0, $this->clock->now());

        if (! $reservation->allowed) {
            return null;
        }

        return $callback();
    }
}
