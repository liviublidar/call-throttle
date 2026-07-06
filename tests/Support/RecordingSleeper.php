<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests\Support;

use ZeroxBliv\CallThrottle\Contracts\Sleeper;

/**
 * Records the requested sleeps instead of really sleeping, and advances the
 * shared FakeClock so subsequent reservations see time move forward.
 */
final class RecordingSleeper implements Sleeper
{
    /** @var array<int, float> */
    public array $sleeps = [];

    public function __construct(private readonly FakeClock $clock)
    {
    }

    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->sleeps[] = $seconds;
        $this->clock->advance($seconds);
    }

    public function totalSlept(): float
    {
        return array_sum($this->sleeps);
    }
}
