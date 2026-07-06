<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Laravel;

use Illuminate\Contracts\Foundation\Application;
use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\LimiterRegistry;
use ZeroxBliv\CallThrottle\Store\DatabaseStore;
use ZeroxBliv\CallThrottle\Store\FileStore;
use ZeroxBliv\CallThrottle\Store\RedisStore;
use ZeroxBliv\CallThrottle\Throttle;
use ZeroxBliv\CallThrottle\ThrottleBuilder;

/**
 * Builds throttlers wired to the configured coordination driver, sourcing
 * connection details from the application's own config.
 */
final class CallThrottleManager
{
    private ?string $driver = null;

    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Select a specific configured driver for the next throttler, e.g.
     * CallThrottle::store('redis')->for('id').
     */
    public function store(string $name): self
    {
        $clone = clone $this;
        $clone->driver = $name;

        return $clone;
    }

    /**
     * A builder pre-loaded with the (default or selected) store for an ad-hoc,
     * unshared limiter. For shared limiters, define them in config and use
     * limiter() instead.
     */
    public function for(string $id): ThrottleBuilder
    {
        return Throttle::for($id)->store($this->resolveStore());
    }

    /**
     * A registered shared limiter, referenced by name. The rate comes from the
     * definition bound to the id at boot — call sites never restate it.
     */
    public function limiter(string $id): Throttle
    {
        return $this->app->make(LimiterRegistry::class)->limiter($id);
    }

    private function resolveStore(): Store
    {
        $name = $this->driver ?? (string) $this->app['config']->get('call-throttle.default', 'file');
        $config = $this->app['config']->get("call-throttle.stores.{$name}");

        if (! is_array($config)) {
            throw new \InvalidArgumentException("Call-throttle store [{$name}] is not configured.");
        }

        return match ($config['driver'] ?? null) {
            'file' => $this->makeFileStore($config),
            'redis' => $this->makeRedisStore($config),
            'database' => $this->makeDatabaseStore($config),
            default => throw new \InvalidArgumentException(
                "Unsupported call-throttle driver [".($config['driver'] ?? 'null')."]."
            ),
        };
    }

    /** @param array<string, mixed> $config */
    private function makeFileStore(array $config): Store
    {
        return new FileStore($config['path'] ?? storage_path('framework/call-throttle'));
    }

    /** @param array<string, mixed> $config */
    private function makeRedisStore(array $config): Store
    {
        $connection = $this->app['redis']->connection($config['connection'] ?? 'default');

        return new RedisStore($connection->client());
    }

    /** @param array<string, mixed> $config */
    private function makeDatabaseStore(array $config): Store
    {
        $pdo = $this->app['db']->connection($config['connection'] ?? null)->getPdo();

        return new DatabaseStore(
            $pdo,
            $config['state_table'] ?? 'call_throttle_limiter_state',
            $config['config_table'] ?? 'call_throttle_limiters',
        );
    }

    /**
     * The store for the default (or currently-selected) driver — used to build
     * the shared limiter registry.
     */
    public function defaultStore(): Store
    {
        return $this->resolveStore();
    }
}
