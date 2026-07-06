<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests;

use PHPUnit\Framework\TestCase;
use ZeroxBliv\CallThrottle\RateLimit;

final class RateLimitTest extends TestCase
{
    public function test_emission_interval_is_period_over_count(): void
    {
        $this->assertEqualsWithDelta(0.25, RateLimit::perSecond(4)->emissionInterval(), 1e-9);
        $this->assertEqualsWithDelta(0.5, RateLimit::of(120, 60)->emissionInterval(), 1e-9);
    }

    public function test_factories_set_the_period(): void
    {
        $this->assertSame(1.0, RateLimit::perSecond(1)->periodSeconds);
        $this->assertSame(60.0, RateLimit::perMinute(1)->periodSeconds);
        $this->assertSame(3600.0, RateLimit::perHour(1)->periodSeconds);
    }

    public function test_rejects_non_positive_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimit(0, 1.0);
    }

    public function test_rejects_non_positive_period(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimit(1, 0.0);
    }

    public function test_from_string_parses_count_and_period(): void
    {
        $this->assertTrue(RateLimit::fromString('100/minute')->equals(RateLimit::perMinute(100)));
        $this->assertTrue(RateLimit::fromString('4/second')->equals(RateLimit::perSecond(4)));
        $this->assertTrue(RateLimit::fromString('100/60')->equals(RateLimit::of(100, 60.0)));
    }

    public function test_from_string_rejects_malformed_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RateLimit::fromString('100 per minute');
    }

    public function test_serialize_round_trips(): void
    {
        $rate = RateLimit::of(7, 2.5);

        $this->assertTrue(RateLimit::deserialize($rate->serialize())->equals($rate));
    }

    public function test_equals_compares_count_and_period(): void
    {
        $this->assertTrue(RateLimit::perSecond(4)->equals(RateLimit::of(4, 1.0)));
        $this->assertFalse(RateLimit::perSecond(4)->equals(RateLimit::perSecond(5)));
        $this->assertFalse(RateLimit::perSecond(4)->equals(RateLimit::perMinute(4)));
    }
}
