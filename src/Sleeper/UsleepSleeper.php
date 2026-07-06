<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Sleeper;

use ZeroxBliv\CallThrottle\Contracts\Sleeper;

final class UsleepSleeper implements Sleeper
{
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        usleep((int) ceil($seconds * 1_000_000));
    }
}
