<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests\Support;

use ZeroxBliv\CallThrottle\Contracts\Clock;

final class FakeClock implements Clock
{
    public function __construct(private float $now = 1000.0)
    {
    }

    public function now(): float
    {
        return $this->now;
    }

    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }
}
