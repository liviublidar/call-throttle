<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Contracts;

use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Reservation;

interface Store
{
    /**
     * Atomically attempt to reserve one slot for $key under $limit at time $now.
     *
     * The read-modify-write of the limiter state MUST be atomic with respect to
     * other concurrent processes sharing the same backend.
     *
     * - If the required wait is within $maxWaitSeconds, the slot is reserved and
     *   the returned Reservation is allowed; the caller should sleep
     *   Reservation::$waitSeconds and then proceed.
     * - Otherwise the state is left untouched and the Reservation is not allowed;
     *   $waitSeconds then reports how long until a slot would have been free.
     */
    public function reserve(string $key, RateLimit $limit, float $maxWaitSeconds, float $now): Reservation;

    /**
     * Bind a rate definition to a limiter id in the shared backend.
     *
     * This is what lets independent processes share one limiter regardless of
     * start-up order: the first to register writes the definition; later ones
     * read it back.
     *
     * - When $overwrite is false: writes $limit only if the id has no definition
     *   yet, and returns the effective definition (the pre-existing one if any,
     *   otherwise $limit). The write-or-read MUST be atomic.
     * - When $overwrite is true: always writes $limit and returns it.
     */
    public function provision(string $id, RateLimit $limit, bool $overwrite = false): RateLimit;
}
