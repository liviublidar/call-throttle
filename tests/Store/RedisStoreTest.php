<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests\Store;

use PHPUnit\Framework\TestCase;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\RedisStore;

/**
 * These run only when a Redis endpoint is available (set REDIS_URL, e.g.
 * tcp://127.0.0.1:6379). They are skipped otherwise, including in the default
 * local environment which has no redis extension or server.
 */
final class RedisStoreTest extends TestCase
{
    private object $client;

    protected function setUp(): void
    {
        $url = getenv('REDIS_URL') ?: ($_ENV['REDIS_URL'] ?? '');

        if ($url === '' || ! class_exists(\Predis\Client::class)) {
            $this->markTestSkipped('Set REDIS_URL and install predis/predis to run the Redis store tests.');
        }

        try {
            $client = new \Predis\Client($url);
            $client->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not connect to Redis: '.$e->getMessage());
        }

        $client->flushdb();
        $this->client = $client;
    }

    public function test_reserve_paces_calls_using_the_redis_clock(): void
    {
        $store = new RedisStore($this->client, 'call-throttle-test:');
        $limit = RateLimit::perSecond(4);

        $allowed = 0;
        for ($i = 0; $i < 10; $i++) {
            // Non-blocking: the Redis TIME clock decides "now".
            if ($store->reserve('api', $limit, 0.0, 0.0)->allowed) {
                $allowed++;
            }
        }

        // The burst allows up to `count` immediately.
        $this->assertSame(4, $allowed);
    }

    public function test_blocking_reserve_returns_a_positive_wait(): void
    {
        $store = new RedisStore($this->client, 'call-throttle-test:');
        $limit = RateLimit::perSecond(4);

        for ($i = 0; $i < 4; $i++) {
            $store->reserve('api', $limit, 0.0, 0.0);
        }

        $reservation = $store->reserve('api', $limit, 5.0, 0.0);

        $this->assertTrue($reservation->allowed);
        $this->assertGreaterThan(0.0, $reservation->waitSeconds);
        $this->assertLessThanOrEqual(0.3, $reservation->waitSeconds);
    }
}
