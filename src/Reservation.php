<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle;

/**
 * The outcome of a Store::reserve() call.
 */
final readonly class Reservation
{
    public function __construct(
        public bool $allowed,
        public float $waitSeconds,
    ) {
    }
}
