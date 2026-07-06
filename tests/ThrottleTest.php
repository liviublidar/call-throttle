<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests;

use PHPUnit\Framework\TestCase;
use ZeroxBliv\CallThrottle\Exceptions\RateLimitExceededException;
use ZeroxBliv\CallThrottle\Store\ArrayStore;
use ZeroxBliv\CallThrottle\Tests\Support\FakeClock;
use ZeroxBliv\CallThrottle\Tests\Support\RecordingSleeper;
use ZeroxBliv\CallThrottle\Throttle;
use ZeroxBliv\CallThrottle\ThrottleBuilder;

final class ThrottleTest extends TestCase
{
    private FakeClock $clock;
    private RecordingSleeper $sleeper;

    protected function setUp(): void
    {
        $this->clock = new FakeClock(1000.0);
        $this->sleeper = new RecordingSleeper($this->clock);
    }

    private function builder(string $id = 'test'): ThrottleBuilder
    {
        return Throttle::for($id)
            ->allow(4)->per('second')
            ->store(new ArrayStore())
            ->clock($this->clock)
            ->sleeper($this->sleeper);
    }

    public function test_run_returns_the_callback_value(): void
    {
        $throttle = $this->builder()->build();

        $this->assertSame('payload', $throttle->run(fn () => 'payload'));
    }

    public function test_run_rethrows_callback_exceptions(): void
    {
        $throttle = $this->builder()->build();

        $this->expectException(\DomainException::class);
        $throttle->run(function (): void {
            throw new \DomainException('boom');
        });
    }

    public function test_initial_burst_of_count_runs_without_waiting(): void
    {
        $throttle = $this->builder()->build();

        for ($i = 0; $i < 4; $i++) {
            $throttle->run(fn () => $i);
        }

        $this->assertSame([], $this->sleeper->sleeps);
    }

    public function test_calls_beyond_the_burst_are_paced_by_the_emission_interval(): void
    {
        $throttle = $this->builder()->build();

        for ($i = 0; $i < 4; $i++) {
            $throttle->run(fn () => null);
        }

        $throttle->run(fn () => null); // 5th
        $throttle->run(fn () => null); // 6th

        $this->assertCount(2, $this->sleeper->sleeps);
        $this->assertEqualsWithDelta(0.25, $this->sleeper->sleeps[0], 1e-9);
        $this->assertEqualsWithDelta(0.25, $this->sleeper->sleeps[1], 1e-9);
    }

    public function test_tokens_refill_over_time(): void
    {
        $throttle = $this->builder()->build();

        for ($i = 0; $i < 4; $i++) {
            $throttle->run(fn () => null);
        }

        // Let a full period pass: the burst allowance is restored.
        $this->clock->advance(1.0);

        for ($i = 0; $i < 4; $i++) {
            $throttle->run(fn () => null);
        }

        $this->assertSame([], $this->sleeper->sleeps);
    }

    public function test_attempt_runs_within_burst_and_returns_value(): void
    {
        $throttle = $this->builder()->build();

        $this->assertSame('ok', $throttle->attempt(fn () => 'ok'));
    }

    public function test_attempt_returns_null_and_does_not_wait_when_limited(): void
    {
        $throttle = $this->builder()->build();
        $ran = 0;

        for ($i = 0; $i < 4; $i++) {
            $throttle->attempt(function () use (&$ran) {
                $ran++;

                return 'ok';
            });
        }

        $result = $throttle->attempt(function () use (&$ran) {
            $ran++;

            return 'ok';
        });

        $this->assertNull($result);
        $this->assertSame(4, $ran);
        $this->assertSame([], $this->sleeper->sleeps);
    }

    public function test_throw_on_limit_throws_instead_of_waiting(): void
    {
        $throttle = $this->builder()->throwOnLimit()->build();

        for ($i = 0; $i < 4; $i++) {
            $throttle->run(fn () => null);
        }

        $this->expectException(RateLimitExceededException::class);
        $throttle->run(fn () => null);
    }

    public function test_run_throws_when_required_wait_exceeds_max_wait(): void
    {
        $throttle = $this->builder()->maxWait(0.1)->build();

        for ($i = 0; $i < 4; $i++) {
            $throttle->run(fn () => null);
        }

        try {
            $throttle->run(fn () => null);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('test', $e->limiterId);
            $this->assertEqualsWithDelta(0.25, $e->waitSeconds, 1e-9);
        }
    }

    public function test_build_requires_a_store(): void
    {
        $this->expectException(\LogicException::class);
        Throttle::for('x')->allow(4)->per('second')->build();
    }

    public function test_build_requires_a_rate(): void
    {
        $this->expectException(\LogicException::class);
        Throttle::for('x')->store(new ArrayStore())->build();
    }
}
