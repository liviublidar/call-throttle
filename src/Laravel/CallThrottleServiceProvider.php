<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Laravel;

use Illuminate\Support\ServiceProvider;
use ZeroxBliv\CallThrottle\LimiterRegistry;
use ZeroxBliv\CallThrottle\RateLimit;

final class CallThrottleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/call-throttle.php', 'call-throttle');

        $this->app->singleton(CallThrottleManager::class, static fn ($app) => new CallThrottleManager($app));
        $this->app->alias(CallThrottleManager::class, 'call-throttle');

        $this->app->singleton(LimiterRegistry::class, static function ($app) {
            return new LimiterRegistry($app->make(CallThrottleManager::class)->defaultStore());
        });
    }

    public function boot(): void
    {
        $this->registerConfiguredLimiters();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/call-throttle.php' => $this->app->configPath('call-throttle.php'),
        ], 'call-throttle-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/create_call_throttle_tables.php.stub' => $this->migrationPath(),
        ], 'call-throttle-migrations');
    }

    /**
     * Bind each configured limiter's rate to its id in the shared store, at boot.
     */
    private function registerConfiguredLimiters(): void
    {
        $registry = $this->app->make(LimiterRegistry::class);

        /** @var array<string, mixed> $limiters */
        $limiters = (array) $this->app['config']->get('call-throttle.limiters', []);

        foreach ($limiters as $id => $definition) {
            if ($registry->has($id)) {
                continue;
            }

            [$rate, $maxWait, $throw] = $this->parseDefinition($definition);
            $registry->register($id, $rate, $maxWait, $throw);
        }
    }

    /**
     * @param  string|array<string, mixed>  $definition
     * @return array{0: RateLimit, 1: float, 2: bool}
     */
    private function parseDefinition(string|array $definition): array
    {
        if (is_string($definition)) {
            return [RateLimit::fromString($definition), INF, false];
        }

        $rate = isset($definition['rate'])
            ? RateLimit::fromString((string) $definition['rate'])
            : new RateLimit((int) $definition['allow'], RateLimit::periodToSeconds($definition['per']));

        $maxWait = array_key_exists('max_wait', $definition) ? (float) $definition['max_wait'] : INF;
        $throw = (bool) ($definition['throw'] ?? false);

        return [$rate, $maxWait, $throw];
    }

    private function migrationPath(): string
    {
        return $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_call_throttle_tables.php');
    }
}
