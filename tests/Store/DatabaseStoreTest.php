<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests\Store;

use PHPUnit\Framework\TestCase;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\DatabaseStore;

final class DatabaseStoreTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function test_create_schema_and_reserve_paces_calls(): void
    {
        $store = new DatabaseStore($this->pdo);
        $store->createSchema();

        $limit = RateLimit::perSecond(4);
        $now = 1000.0;
        $allowed = 0;

        for ($i = 0; $i < 10; $i++) {
            if ($store->reserve('api', $limit, 0.0, $now)->allowed) {
                $allowed++;
            }
        }

        $this->assertSame(4, $allowed);

        // A slot frees up after the emission interval.
        $this->assertTrue($store->reserve('api', $limit, 0.0, $now + 0.25)->allowed);
    }

    public function test_reports_wait_time_when_refused(): void
    {
        $store = new DatabaseStore($this->pdo);
        $store->createSchema();

        $limit = RateLimit::perSecond(4);
        $now = 1000.0;

        for ($i = 0; $i < 4; $i++) {
            $store->reserve('api', $limit, 0.0, $now);
        }

        $reservation = $store->reserve('api', $limit, 0.0, $now);

        $this->assertFalse($reservation->allowed);
        $this->assertEqualsWithDelta(0.25, $reservation->waitSeconds, 1e-9);
    }

    public function test_blocking_reserve_within_budget_is_allowed_with_wait(): void
    {
        $store = new DatabaseStore($this->pdo);
        $store->createSchema();

        $limit = RateLimit::perSecond(4);
        $now = 1000.0;

        for ($i = 0; $i < 4; $i++) {
            $store->reserve('api', $limit, 0.0, $now);
        }

        $reservation = $store->reserve('api', $limit, 5.0, $now);

        $this->assertTrue($reservation->allowed);
        $this->assertEqualsWithDelta(0.25, $reservation->waitSeconds, 1e-9);
    }

    public function test_provision_persists_the_definition_first_writer_wins(): void
    {
        $store = new DatabaseStore($this->pdo);
        $store->createSchema();

        $effective = $store->provision('api', RateLimit::perMinute(100));
        $this->assertTrue($effective->equals(RateLimit::perMinute(100)));

        // Re-provisioning with a different rate returns the stored definition.
        $seen = $store->provision('api', RateLimit::perSecond(4));
        $this->assertTrue($seen->equals(RateLimit::perMinute(100)));
    }

    public function test_provision_overwrite_replaces_the_definition(): void
    {
        $store = new DatabaseStore($this->pdo);
        $store->createSchema();

        $store->provision('api', RateLimit::perMinute(100));
        $seen = $store->provision('api', RateLimit::perSecond(5), overwrite: true);

        $this->assertTrue($seen->equals(RateLimit::perSecond(5)));
    }
}
