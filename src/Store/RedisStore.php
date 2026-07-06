<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Store;

use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Reservation;

/**
 * Redis-backed store. The whole reserve-or-refuse decision runs inside a single
 * Lua script, so it is atomic in one round-trip and uses the Redis server clock
 * as the single source of truth across every worker.
 *
 * Accepts either the phpredis \Redis client or a \Predis\Client — both expose an
 * eval() method (their signatures differ and are handled below).
 */
final class RedisStore implements Store
{
    private const SCRIPT = <<<'LUA'
    local key = KEYS[1]
    local emission_interval = tonumber(ARGV[1])
    local burst_tolerance = tonumber(ARGV[2])
    local max_wait = tonumber(ARGV[3])
    local ttl_ms = tonumber(ARGV[4])

    local time = redis.call('TIME')
    local now = tonumber(time[1]) + (tonumber(time[2]) / 1000000)

    local tat = redis.call('GET', key)
    if tat then tat = tonumber(tat) else tat = now end

    local new_tat = math.max(tat, now) + emission_interval
    local wait = (new_tat - burst_tolerance) - now

    local allowed = 0
    if wait <= max_wait then
        allowed = 1
        redis.call('SET', key, new_tat, 'PX', ttl_ms)
    end

    if wait < 0 then wait = 0 end

    return { allowed, math.floor((wait * 1000) + 0.5) }
    LUA;

    private const PROVISION_SCRIPT = <<<'LUA'
    local key = KEYS[1]
    local value = ARGV[1]
    local overwrite = ARGV[2]

    if overwrite == '0' then
        local existing = redis.call('GET', key)
        if existing then return existing end
    end

    redis.call('SET', key, value)
    return value
    LUA;

    public function __construct(
        private readonly object $client,
        private readonly string $prefix = 'call-throttle:',
    ) {
    }

    public function reserve(string $key, RateLimit $limit, float $maxWaitSeconds, float $now): Reservation
    {
        $ttlMs = (int) ceil(($limit->periodSeconds + $limit->emissionInterval()) * 1000) + 1000;

        $args = [
            $this->format($limit->emissionInterval()),
            $this->format($limit->periodSeconds),
            is_finite($maxWaitSeconds) ? $this->format($maxWaitSeconds) : '1e12',
            (string) $ttlMs,
        ];

        $result = $this->evalScript(self::SCRIPT, [$this->prefix.$key], $args);

        $allowed = (bool) (int) ($result[0] ?? 0);
        $waitSeconds = ((int) ($result[1] ?? 0)) / 1000;

        return new Reservation($allowed, $waitSeconds);
    }

    public function provision(string $id, RateLimit $limit, bool $overwrite = false): RateLimit
    {
        $result = $this->evalScript(
            self::PROVISION_SCRIPT,
            [$this->prefix.'config:'.$id],
            [$limit->serialize(), $overwrite ? '1' : '0'],
        );

        $stored = $result[0] ?? null;

        return is_string($stored) ? RateLimit::deserialize($stored) : $limit;
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $args
     * @return array<int, mixed>
     */
    private function evalScript(string $script, array $keys, array $args): array
    {
        $numKeys = count($keys);

        if ($this->client instanceof \Predis\ClientInterface) {
            return (array) $this->client->eval($script, $numKeys, ...array_merge($keys, $args));
        }

        // phpredis \Redis / \RedisCluster: eval(script, [keys..., args...], numKeys)
        return (array) $this->client->eval($script, array_merge($keys, $args), $numKeys);
    }
}
