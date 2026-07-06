<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Clock;

use ZeroxBliv\CallThrottle\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): float
    {
        return microtime(true);
    }
}
