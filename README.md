# call-throttle

A distributed **call rate limiter** for PHP. It paces callback execution to a fixed rate
(e.g. *4 per second*) and enforces that rate **across independent processes** — multiple queue
workers, cron jobs, CLI runs — that share a limiter id. Coordination state lives in a shared
backend (**Redis**, **file**, or **database**), so every worker agrees on the global rate.

You wrap the work you want throttled in a callback; the limiter decides *when* it runs. By default
it blocks until a slot is free and then runs it, returning whatever your callback returns.

```php
use ZeroxBliv\CallThrottle\Throttle;
use ZeroxBliv\CallThrottle\Store\RedisStore;

// Configure once → a frozen, reusable, id-bound throttler.
$throttle = Throttle::for('external-api')   // limiterId, shared across all workers
    ->allow(4)->per('second')               // 4 per second
    ->store(new RedisStore($redis))
    ->maxWait(10)                           // block at most 10s before throwing (optional)
    ->build();

// $response IS the callback's return value — usable exactly as a direct call.
$response = $throttle->run(fn () => Http::get('https://api.example.com/foo'));
$data = $response->json();
```

## Install

```bash
composer require 0xbliv/call-throttle
```

Requires PHP 8.2+.

## How it works

The limiter uses the **GCRA** (Generic Cell Rate Algorithm), a smooth leaky-rate limiter. A limit
of `count` per `period`:

- allows an **initial burst** of up to `count` calls, then
- paces subsequent calls one every `period / count` seconds.

The entire per-limiter state is a single timestamp, updated **atomically** in the chosen backend, so
concurrent workers coordinate correctly.

## Modes

| Call | Slot free | Throttled |
|------|-----------|-----------|
| `run($cb)` | sleeps ≤ `maxWait`, runs, returns value | throws `RateLimitExceededException` |
| `throwOnLimit()->run($cb)` | runs, returns value | throws immediately (no wait) |
| `attempt($cb)` | runs, returns value | returns `null`, callback not run, no wait |

Exceptions thrown by your callback propagate unchanged.

## Immutability

`Throttle::for()` returns a **builder** — the only place the store and rate are set. `build()` freezes
it into a `final readonly Throttle` that exposes `run()` / `attempt()` plus the wait-policy copies
`withMaxWait()` / `withThrowOnLimit()` (which return a *new* instance) — but **no store or rate
setters**. A live throttler's store and rate can never be reset, so it is safe to reuse and inject.

## Sharing a limiter across workers (the registry)

Different processes — even different codebases — that call the *same* API must share *one* limit,
and they don't start in a known order. The wrong way is to restate the rate at every call site: two
callers can disagree, silently corrupting the pacing.

Instead, **bind the rate to the id once and register it**. Registration provisions the rate into the
shared backend: the first process to register writes the definition; every later process, in any
order, **adopts it** from the store. A caller that registers a *different* rate for the same id gets
a `LimiterConflictException` — never silent drift. Call sites then reference the limiter **by name
only**.

```php
use ZeroxBliv\CallThrottle\{LimiterRegistry, RateLimit};
use ZeroxBliv\CallThrottle\Store\RedisStore;

// Once, at boot, in every worker (same store, same id):
$registry = new LimiterRegistry(new RedisStore($redis));
$registry->register('external-api', RateLimit::perMinute(100));

// Anywhere, in any process — no rate here, so it can't drift:
$response = $registry->limiter('external-api')->run(fn () => Http::get('https://api.example.com/foo'));
$registry->limiter('external-api')->run(fn () => Http::post('https://api.example.com/bar'));
```

`limiter('id')` returns the immutable `Throttle` bound to the registered rate. To vary *wait policy*
per caller (not the rate), use `withMaxWait()` / `withThrowOnLimit()` / `attempt()`:

```php
$registry->limiter('external-api')->withMaxWait(30)->run(fn () => ...);   // this caller waits up to 30s
$registry->limiter('external-api')->withThrowOnLimit()->run(fn () => ...); // this caller fails fast
```

To change a rate on purpose, `redefine('id', $newRate)` overwrites the stored definition.

Every feature is demonstrated under [`examples/`](examples) (see its
[README](examples/README.md)) — runnable plain-PHP scripts in [`examples/php/`](examples/php) and
drop-in Laravel snippets in [`examples/laravel/`](examples/laravel). For the shared-across-processes
demo, run [`examples/php/shared_workers/worker.php`](examples/php/shared_workers/worker.php) in
several terminals at once.

> For a genuinely one-off, unshared limiter you can still configure inline with
> `Throttle::for('id')->allow(4)->per('second')->store($store)->run(...)` — but for anything shared,
> register it.

## Stores

```php
use ZeroxBliv\CallThrottle\Store\{RedisStore, FileStore, DatabaseStore};

new RedisStore($redis);                 // \Redis (phpredis) or \Predis\Client — atomic Lua, uses Redis clock
new FileStore('/var/run/throttle');     // flock; processes sharing a filesystem/host
new DatabaseStore($pdo);                // PDO; atomic transaction, row lock (FOR UPDATE) on MySQL/Postgres
```

- **Redis** — best for multi-host fleets; the reserve runs entirely in a Lua script.
- **File** — zero infrastructure; only coordinates processes on a shared filesystem/host.
- **Database** — reuse an existing DB. Call `$store->createSchema()` once, or run the Laravel migration.

## Laravel

The Laravel bridge is **optional** — no `illuminate/*` package is a hard dependency, so plain-PHP
users pull nothing extra. It supports **Laravel 10, 11, 12 and 13** (enforced by a `conflict` rule on
`illuminate/support < 10`, so an unsupported version fails at `composer` time rather than at runtime).

The package auto-registers. Publish the config (and, for the database driver, the migration):

```bash
php artisan vendor:publish --tag=call-throttle-config
php artisan vendor:publish --tag=call-throttle-migrations   # database driver only
```

Pick the driver with `CALL_THROTTLE_DRIVER=file|redis|database`. The `file` driver needs no setup
(state is stored privately under `storage/framework/call-throttle`); `redis` and `database` reuse
your app's existing connections (`REDIS_*` / `DB_*`).

Define shared limiters once in config; the service provider registers them at boot:

```php
// config/call-throttle.php
'limiters' => [
    'external-api' => '100/minute',
    'reports'      => ['rate' => '30/minute', 'max_wait' => 10],
    'webhooks'     => ['allow' => 5, 'per' => 'second', 'throw' => true],
],
```

A rate is `count/period`, where `period` is `second` · `minute` · `hour` · `day` (aliases `s` · `min`
· `h` · `d`) or a raw number of seconds like `100/60`. There is no `week`/`month` keyword — use raw
seconds. Keywords are singular (`minute`, not `minutes`); an unknown one throws at boot.

Then reference them by name anywhere — no rate at the call site:

```php
use ZeroxBliv\CallThrottle\Laravel\Facades\CallThrottle;

$response = CallThrottle::limiter('external-api')
    ->run(fn () => Http::get('https://api.example.com/foo'));

// Per-caller wait policy (not the rate):
CallThrottle::limiter('external-api')->withMaxWait(30)->run(fn () => ...);
```

For an ad-hoc, unshared limiter you can still configure inline:

```php
CallThrottle::for('one-off')->allow(4)->per('second')->run(fn () => ...);
CallThrottle::store('redis')->for('one-off')->allow(4)->per('second')->run(fn () => ...);
```

## Development

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan level 6 (Larastan makes the Laravel bridge type-aware)
```

Redis tests are skipped unless `REDIS_URL` is set (e.g. `REDIS_URL=tcp://127.0.0.1:6379`).

## License

MIT.
