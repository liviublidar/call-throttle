<?php

declare(strict_types=1);

/*
 * Registering limiters in code instead of (or in addition to) config.
 *
 * Useful when a limiter's rate is dynamic — e.g. read from the database or an
 * API plan. Resolve the shared LimiterRegistry from the container and register
 * in boot(). Config-defined limiters are already registered by the package's own
 * provider; here you add or override at runtime.
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use ZeroxBliv\CallThrottle\LimiterRegistry;
use ZeroxBliv\CallThrottle\RateLimit;

final class LimiterRegistrationProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var LimiterRegistry $registry */
        $registry = $this->app->make(LimiterRegistry::class);

        // Register a new shared limiter with a rate computed at runtime.
        $plan = config('services.partner.plan_rate', '200/minute');
        if (! $registry->has('partner-api')) {
            $registry->register('partner-api', RateLimit::fromString($plan), maxWait: 15.0);
        }

        // Intentionally change an existing limiter's rate (overwrites the store).
        if (app()->environment('local')) {
            $registry->redefine('external-api', RateLimit::perSecond(50));
        }

        // The registry is also reachable via the manager / facade accessor:
        //   app(\ZeroxBliv\CallThrottle\Laravel\CallThrottleManager::class)->limiter('partner-api');
        //   app('call-throttle')->limiter('partner-api');
    }

    public function usageAnywhere(): void
    {
        // Once registered, reference by name from anywhere via the container:
        $throttle = $this->app->make(LimiterRegistry::class)->limiter('partner-api');
        $throttle->run(fn () => /* ... */ null);
    }
}
