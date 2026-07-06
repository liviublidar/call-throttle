<?php

declare(strict_types=1);

/*
 * 02 — Every store / backend
 *
 * The same limiter works over any Store. Pick the one that matches how your
 * processes are spread out:
 *   - ArrayStore    : single process only (tests / examples)
 *   - FileStore     : processes sharing a filesystem / host
 *   - DatabaseStore : any host, reuse an existing SQL database (PDO)
 *   - RedisStore    : any host, best for high-contention fleets
 *
 *     php examples/php/02_stores.php
 */

require __DIR__.'/../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\ArrayStore;
use ZeroxBliv\CallThrottle\Store\DatabaseStore;
use ZeroxBliv\CallThrottle\Store\FileStore;
use ZeroxBliv\CallThrottle\Store\RedisStore;
use ZeroxBliv\CallThrottle\Throttle;

function demo(string $label, \ZeroxBliv\CallThrottle\Contracts\Store $store): void
{
    $throttle = Throttle::for('stores-demo')->allow(2)->per('second')->store($store)->build();
    $first = $throttle->attempt(fn () => true) !== null;
    $second = $throttle->attempt(fn () => true) !== null;
    $third = $throttle->attempt(fn () => true) !== null; // over the burst of 2
    printf("%-13s burst allowed: [%s, %s, %s]\n", $label, b($first), b($second), b($third));
}

function b(bool $v): string
{
    return $v ? 'yes' : 'no';
}

// --- ArrayStore: in-memory, single process ---
demo('ArrayStore', new ArrayStore());

// --- FileStore: coordinates processes on a shared filesystem ---
$dir = sys_get_temp_dir().'/call-throttle-ex-stores';
demo('FileStore', new FileStore($dir));
array_map('unlink', glob($dir.'/*') ?: []);
@rmdir($dir);

// --- DatabaseStore: PDO. createSchema() builds the tables for non-Laravel use ---
if (in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
    $pdo = new \PDO('sqlite::memory:');
    $dbStore = new DatabaseStore($pdo); // optional: new DatabaseStore($pdo, 'state_table', 'limiters_table')
    $dbStore->createSchema();
    demo('DatabaseStore', $dbStore);
} else {
    echo "DatabaseStore  skipped (pdo_sqlite not available)\n";
}

// --- RedisStore: accepts phpredis (\Redis) OR a \Predis\Client ---
//
//   // phpredis:
//   $redis = new \Redis();
//   $redis->connect('127.0.0.1', 6379);
//   $store = new RedisStore($redis);
//
//   // Predis:
//   $store = new RedisStore(new \Predis\Client('tcp://127.0.0.1:6379'));
//
// Runs here only if REDIS_URL is set and predis is installed.
$redisUrl = getenv('REDIS_URL') ?: '';
if ($redisUrl !== '' && class_exists(\Predis\Client::class)) {
    try {
        $client = new \Predis\Client($redisUrl);
        $client->ping();
        $client->del('call-throttle:stores-demo');
        demo('RedisStore', new RedisStore($client));
    } catch (\Throwable $e) {
        echo 'RedisStore     skipped ('.$e->getMessage().")\n";
    }
} else {
    echo "RedisStore     skipped (set REDIS_URL to run)\n";
}

// RateLimit for a store can be built any of these ways (see 03_rate_definitions.php):
$_ = RateLimit::perSecond(2);
