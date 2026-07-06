<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use ZeroxBliv\CallThrottle\Laravel\CallThrottleManager;
use ZeroxBliv\CallThrottle\Throttle;
use ZeroxBliv\CallThrottle\ThrottleBuilder;

/**
 * @method static Throttle limiter(string $id)
 * @method static ThrottleBuilder for(string $id)
 * @method static CallThrottleManager store(string $name)
 *
 * @see CallThrottleManager
 */
final class CallThrottle extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'call-throttle';
    }
}
