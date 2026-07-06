<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests;

use PHPUnit\Framework\TestCase;
use ZeroxBliv\CallThrottle\Exceptions\LimiterConflictException;
use ZeroxBliv\CallThrottle\Exceptions\RateLimitExceededException;
use ZeroxBliv\CallThrottle\Exceptions\UnknownLimiterException;
use ZeroxBliv\CallThrottle\LimiterRegistry;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\ArrayStore;
use ZeroxBliv\CallThrottle\Tests\Support\FakeClock;
use ZeroxBliv\CallThrottle\Tests\Support\RecordingSleeper;

final class LimiterRegistryTest extends TestCase
{
    public function test_reference_by_name_runs_and_returns_the_callback_value(): void
    {
        $registry = new LimiterRegistry(new ArrayStore());
        $registry->register('api', RateLimit::perSecond(4));

        $this->assertSame('ok', $registry->limiter('api')->run(fn () => 'ok'));
    }

    public function test_unknown_limiter_throws(): void
    {
        $registry = new LimiterRegistry(new ArrayStore());

        $this->expectException(UnknownLimiterException::class);
        $registry->limiter('nope');
    }

    public function test_a_second_process_adopts_the_rate_the_first_provisioned(): void
    {
        $store = new ArrayStore(); // one shared backend, two "processes"
        $first = new LimiterRegistry($store);
        $second = new LimiterRegistry($store);

        $first->register('api', RateLimit::perSecond(4));
        // The second registers the same id — order-independent, no rate at call site drift.
        $second->register('api', RateLimit::perSecond(4));

        $this->assertTrue($second->has('api'));
    }

    public function test_conflicting_definition_throws(): void
    {
        $store = new ArrayStore();
        $first = new LimiterRegistry($store);
        $second = new LimiterRegistry($store);

        $first->register('api', RateLimit::perSecond(4));

        $this->expectException(LimiterConflictException::class);
        $second->register('api', RateLimit::perSecond(10));
    }

    public function test_registries_sharing_a_store_share_one_global_budget(): void
    {
        $store = new ArrayStore();
        $clock = new FakeClock(1000.0);
        $first = new LimiterRegistry($store, $clock, new RecordingSleeper($clock));
        $second = new LimiterRegistry($store, $clock, new RecordingSleeper($clock));

        $first->register('api', RateLimit::perSecond(4));
        $second->register('api', RateLimit::perSecond(4));

        $allowed = 0;
        foreach ([$first, $first, $second, $second, $first, $second] as $registry) {
            if ($registry->limiter('api')->attempt(fn () => true) !== null) {
                $allowed++;
            }
        }

        $this->assertSame(4, $allowed);
    }

    public function test_redefine_overwrites_the_stored_rate(): void
    {
        $store = new ArrayStore();
        $registry = new LimiterRegistry($store);
        $registry->register('api', RateLimit::perSecond(4));

        $registry->redefine('api', RateLimit::perSecond(10));

        // A fresh registry now sees the redefined rate without conflict.
        $other = new LimiterRegistry($store);
        $other->register('api', RateLimit::perSecond(10));
        $this->assertTrue($other->has('api'));
    }

    public function test_per_call_wait_policy_override_keeps_the_shared_rate(): void
    {
        $registry = new LimiterRegistry(new ArrayStore());
        $registry->register('api', RateLimit::perSecond(4));

        // Exhaust the burst (each within-burst call waits 0).
        for ($i = 0; $i < 4; $i++) {
            $registry->limiter('api')->run(fn () => null);
        }

        // Override only the wait policy for this caller — rate is untouched.
        $failFast = $registry->limiter('api')->withThrowOnLimit();

        $this->expectException(RateLimitExceededException::class);
        $failFast->run(fn () => null);
    }
}
