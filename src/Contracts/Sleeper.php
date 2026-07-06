<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Contracts;

interface Sleeper
{
    /**
     * Block the current process for the given number of seconds.
     * Implementations must treat a value <= 0 as a no-op.
     */
    public function sleep(float $seconds): void;
}
