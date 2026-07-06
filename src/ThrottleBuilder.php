<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle;

use ZeroxBliv\CallThrottle\Clock\SystemClock;
use ZeroxBliv\CallThrottle\Contracts\Clock;
use ZeroxBliv\CallThrottle\Contracts\Sleeper;
use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\Sleeper\UsleepSleeper;

/**
 * The mutable configuration surface. This is the only place a throttler's store
 * and rate are set; build() freezes the configuration into an immutable Throttle.
 */
final class ThrottleBuilder
{
    private ?Store $store = null;
    private ?int $count = null;
    private ?float $periodSeconds = null;
    private float $maxWait = INF;
    private bool $throwOnLimit = false;
    private ?Clock $clock = null;
    private ?Sleeper $sleeper = null;

    public function __construct(private readonly string $id)
    {
    }

    /**
     * The number of calls allowed per period. Pair with per().
     */
    public function allow(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * The period the count applies to: a keyword ('second', 'minute', 'hour',
     * 'day'), or a number of seconds.
     */
    public function per(string|int|float $period): self
    {
        $this->periodSeconds = RateLimit::periodToSeconds($period);

        return $this;
    }

    public function store(Store $store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Maximum seconds run() may block before throwing. Defaults to blocking
     * until a slot is free.
     */
    public function maxWait(float $seconds): self
    {
        $this->maxWait = $seconds;

        return $this;
    }

    /**
     * Make run() throw immediately instead of waiting when no slot is free.
     */
    public function throwOnLimit(bool $throw = true): self
    {
        $this->throwOnLimit = $throw;

        return $this;
    }

    public function clock(Clock $clock): self
    {
        $this->clock = $clock;

        return $this;
    }

    public function sleeper(Sleeper $sleeper): self
    {
        $this->sleeper = $sleeper;

        return $this;
    }

    /**
     * Freeze the configuration into an immutable, reusable Throttle.
     */
    public function build(): Throttle
    {
        if ($this->store === null) {
            throw new \LogicException('A store must be configured with store() before building a throttle.');
        }

        if ($this->count === null || $this->periodSeconds === null) {
            throw new \LogicException('A rate must be configured with allow(...)->per(...) before building a throttle.');
        }

        return new Throttle(
            $this->store,
            $this->id,
            new RateLimit($this->count, $this->periodSeconds),
            $this->maxWait,
            $this->throwOnLimit,
            $this->clock ?? new SystemClock(),
            $this->sleeper ?? new UsleepSleeper(),
        );
    }

    /**
     * Convenience terminal: build a one-off throttle and run the callback.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        return $this->build()->run($callback);
    }

    /**
     * Convenience terminal: build a one-off throttle and attempt the callback.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T|null
     */
    public function attempt(callable $callback): mixed
    {
        return $this->build()->attempt($callback);
    }
}
