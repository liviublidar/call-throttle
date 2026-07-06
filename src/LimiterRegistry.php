<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle;

use ZeroxBliv\CallThrottle\Clock\SystemClock;
use ZeroxBliv\CallThrottle\Contracts\Clock;
use ZeroxBliv\CallThrottle\Contracts\Sleeper;
use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\Exceptions\LimiterConflictException;
use ZeroxBliv\CallThrottle\Exceptions\UnknownLimiterException;
use ZeroxBliv\CallThrottle\Sleeper\UsleepSleeper;

/**
 * Binds rate definitions to limiter ids and hands them back by name.
 *
 * Registering a limiter provisions its rate into the shared backend: the first
 * process to register an id writes the definition, and any later process — in
 * any order, in any codebase sharing the same store — reads it back. This is
 * what makes "different callers sharing one limiter" safe: the rate is a
 * property of the id, not of the call site.
 */
final class LimiterRegistry
{
    /** @var array<string, Throttle> */
    private array $limiters = [];

    public function __construct(
        private readonly Store $store,
        private readonly Clock $clock = new SystemClock(),
        private readonly Sleeper $sleeper = new UsleepSleeper(),
    ) {
    }

    /**
     * Register a limiter id with its rate. Adopts the definition already stored
     * for the id if there is one; throws LimiterConflictException if the stored
     * rate disagrees with $rate.
     *
     * $maxWait / $throwOnLimit set the default wait policy for this limiter; a
     * caller can still override the wait policy per call via the returned
     * Throttle's withMaxWait()/withThrowOnLimit() — but never the rate.
     */
    public function register(
        string $id,
        RateLimit $rate,
        float $maxWait = INF,
        bool $throwOnLimit = false,
    ): self {
        $effective = $this->store->provision($id, $rate);

        if (! $effective->equals($rate)) {
            throw new LimiterConflictException($id, $rate, $effective);
        }

        $this->limiters[$id] = new Throttle(
            $this->store,
            $id,
            $effective,
            $maxWait,
            $throwOnLimit,
            $this->clock,
            $this->sleeper,
        );

        return $this;
    }

    /**
     * Intentionally overwrite a limiter's stored definition with a new rate.
     */
    public function redefine(
        string $id,
        RateLimit $rate,
        float $maxWait = INF,
        bool $throwOnLimit = false,
    ): self {
        $effective = $this->store->provision($id, $rate, overwrite: true);

        $this->limiters[$id] = new Throttle(
            $this->store,
            $id,
            $effective,
            $maxWait,
            $throwOnLimit,
            $this->clock,
            $this->sleeper,
        );

        return $this;
    }

    public function has(string $id): bool
    {
        return isset($this->limiters[$id]);
    }

    /**
     * The immutable throttler for a registered id. Reference-by-name only — the
     * rate cannot be changed here.
     */
    public function limiter(string $id): Throttle
    {
        return $this->limiters[$id] ?? throw new UnknownLimiterException($id);
    }
}
