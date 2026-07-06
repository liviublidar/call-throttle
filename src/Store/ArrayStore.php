<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Store;

use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\Gcra;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Reservation;

/**
 * In-memory store. Coordinates only within a single process, so it is intended
 * for tests, examples, and single-process usage — not for distributed pacing.
 */
final class ArrayStore implements Store
{
    /** @var array<string, float> */
    private array $tats = [];

    /** @var array<string, RateLimit> */
    private array $configs = [];

    public function reserve(string $key, RateLimit $limit, float $maxWaitSeconds, float $now): Reservation
    {
        [$allowed, $wait, $newTat] = Gcra::reserve($this->tats[$key] ?? null, $now, $limit, $maxWaitSeconds);

        if ($allowed) {
            $this->tats[$key] = $newTat;
        }

        return new Reservation($allowed, $wait);
    }

    public function provision(string $id, RateLimit $limit, bool $overwrite = false): RateLimit
    {
        if ($overwrite) {
            return $this->configs[$id] = $limit;
        }

        return $this->configs[$id] ??= $limit;
    }
}
