<?php

declare(strict_types=1);

/*
 * 06 — The registry: shared, name-referenced limiters
 *
 * Bind a rate to an id once; every process (any boot order) adopts that same
 * definition from the shared store. Call sites reference by name only, so the
 * rate can never drift. This is the fix for "different processes sharing one API
 * limit".
 *
 *     php examples/php/06_registry.php
 */

require __DIR__.'/../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\Exceptions\LimiterConflictException;
use ZeroxBliv\CallThrottle\Exceptions\RateLimitExceededException;
use ZeroxBliv\CallThrottle\Exceptions\UnknownLimiterException;
use ZeroxBliv\CallThrottle\LimiterRegistry;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\FileStore;

$dir = sys_get_temp_dir().'/call-throttle-ex-registry';
@array_map('unlink', glob($dir.'/*') ?: []);

// --- "Process A" registers the limiter (provisions the rate to the store) ---
$a = new LimiterRegistry(new FileStore($dir));
$a->register('external-api', RateLimit::perSecond(4));
echo "A registered external-api = 4/second\n";

// register() can also set a default wait policy for the limiter:
$a->register('reports', RateLimit::perMinute(30), maxWait: 10.0, throwOnLimit: false);

// --- has(): is an id registered in this registry? ---
printf("A has 'external-api'? %s ; has 'unknown'? %s\n",
    var_export($a->has('external-api'), true), var_export($a->has('unknown'), true));

// --- Reference by name; run() returns the callback value ---
echo $a->limiter('external-api')->run(fn () => "called endpoint A\n");

// --- "Process B" boots later, same store: adopts A's definition ---
$b = new LimiterRegistry(new FileStore($dir));
$b->register('external-api', RateLimit::perSecond(4)); // matches -> fine
echo "B adopted external-api from the shared store\n";

// --- A conflicting definition is rejected loudly (not silently blended) ---
try {
    (new LimiterRegistry(new FileStore($dir)))->register('external-api', RateLimit::perSecond(10));
} catch (LimiterConflictException $e) {
    echo 'Conflict caught: '.$e->getMessage().PHP_EOL;
}

// --- redefine(): intentionally change a stored rate ---
$a->redefine('external-api', RateLimit::perSecond(8));
echo "A redefined external-api = 8/second\n";

// --- Referencing an unregistered id throws ---
try {
    $a->limiter('never-registered')->run(fn () => null);
} catch (UnknownLimiterException $e) {
    echo 'Unknown limiter caught: '.$e->getMessage().PHP_EOL;
}

// --- Per-call wait policy (rate stays fixed) via the returned Throttle ---
$a->redefine('tiny', RateLimit::perSecond(1));
$a->limiter('tiny')->run(fn () => null); // use the single burst slot
try {
    // fail fast instead of waiting:
    $a->limiter('tiny')->withThrowOnLimit()->run(fn () => null);
} catch (RateLimitExceededException $e) {
    echo "withThrowOnLimit(): failed fast on 'tiny'\n";
}
// non-blocking:
$skipped = $a->limiter('tiny')->attempt(fn () => 'ran');
printf("attempt() on exhausted 'tiny' returned %s\n", var_export($skipped, true));
// or wait, but only up to a bound:
$a->limiter('tiny')->withMaxWait(2.0)->run(fn () => null);
echo "withMaxWait(2.0): waited within budget and ran\n";

// cleanup
array_map('unlink', glob($dir.'/*') ?: []);
@rmdir($dir);
