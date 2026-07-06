<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Tests\Store;

use PHPUnit\Framework\TestCase;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\FileStore;

final class FileStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/call-throttle-test-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->dir)) {
            return;
        }

        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    public function test_directory_is_created_on_construction(): void
    {
        new FileStore($this->dir);

        $this->assertDirectoryExists($this->dir);
    }

    public function test_only_count_calls_are_allowed_at_the_same_instant(): void
    {
        $limit = RateLimit::perSecond(4);
        $now = 1000.0;
        $allowed = 0;

        for ($i = 0; $i < 10; $i++) {
            $store = new FileStore($this->dir);
            if ($store->reserve('api', $limit, 0.0, $now)->allowed) {
                $allowed++;
            }
        }

        $this->assertSame(4, $allowed);
    }

    public function test_a_slot_frees_up_after_the_emission_interval(): void
    {
        $store = new FileStore($this->dir);
        $limit = RateLimit::perSecond(4);
        $now = 1000.0;

        for ($i = 0; $i < 4; $i++) {
            $store->reserve('api', $limit, 0.0, $now);
        }

        // Non-blocking now: refused.
        $this->assertFalse($store->reserve('api', $limit, 0.0, $now)->allowed);

        // After one emission interval (0.25s) a slot is free again.
        $this->assertTrue($store->reserve('api', $limit, 0.0, $now + 0.25)->allowed);
    }

    public function test_separate_keys_do_not_interfere(): void
    {
        $store = new FileStore($this->dir);
        $limit = RateLimit::perSecond(1);
        $now = 1000.0;

        $this->assertTrue($store->reserve('a', $limit, 0.0, $now)->allowed);
        $this->assertTrue($store->reserve('b', $limit, 0.0, $now)->allowed);
    }

    public function test_provision_persists_the_definition_first_writer_wins(): void
    {
        $first = new FileStore($this->dir);
        $second = new FileStore($this->dir);

        $effective = $first->provision('api', RateLimit::perMinute(100));
        $this->assertTrue($effective->equals(RateLimit::perMinute(100)));

        $seen = $second->provision('api', RateLimit::perSecond(4));
        $this->assertTrue($seen->equals(RateLimit::perMinute(100)));
    }

    public function test_provision_overwrite_replaces_the_definition(): void
    {
        $store = new FileStore($this->dir);
        $store->provision('api', RateLimit::perMinute(100));

        $seen = $store->provision('api', RateLimit::perSecond(5), overwrite: true);
        $this->assertTrue($seen->equals(RateLimit::perSecond(5)));
    }
}
