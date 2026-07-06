<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Contracts;

interface Clock
{
    /**
     * The current time, in seconds, as a float (like microtime(true)).
     */
    public function now(): float;
}
