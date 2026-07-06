# Examples

Every feature of the library, demonstrated for plain PHP and Laravel.

## Plain PHP (runnable)

Run any of these directly, e.g. `php examples/php/01_quickstart.php`.

| File | Shows |
|------|-------|
| [php/01_quickstart.php](php/01_quickstart.php) | Build a throttle, `run()` a callback, get its return value; reuse + pacing |
| [php/02_stores.php](php/02_stores.php) | Constructing every store: `ArrayStore`, `FileStore`, `DatabaseStore` (`createSchema()`), `RedisStore` (phpredis & Predis) |
| [php/03_rate_definitions.php](php/03_rate_definitions.php) | `RateLimit` factories, `fromString()`, `of()`, `equals()`, and every `per()` form |
| [php/04_overflow_modes.php](php/04_overflow_modes.php) | All four behaviours: block-and-wait, `maxWait`, `throwOnLimit()`, `attempt()` + `RateLimitExceededException` |
| [php/05_return_values_and_exceptions.php](php/05_return_values_and_exceptions.php) | Return-value and exception pass-through |
| [php/06_registry.php](php/06_registry.php) | `LimiterRegistry`: `register`, adopt, conflict, `redefine`, `has`, `limiter`, per-call wait policy, unknown-limiter |
| [php/07_testing_with_fakes.php](php/07_testing_with_fakes.php) | Injecting `clock()` / `sleeper()` for deterministic tests |
| [php/shared_workers/worker.php](php/shared_workers/worker.php) | One shared limiter across separate processes — run in several terminals |

## Laravel (drop-in snippets)

Illustrative `App\` classes; copy into a Laravel app.

| File | Shows |
|------|-------|
| [laravel/config-call-throttle.php](laravel/config-call-throttle.php) | Every driver selection and limiter-definition form |
| [laravel/Jobs/SyncExternalResource.php](laravel/Jobs/SyncExternalResource.php) | Queue job sharing one limit across all workers |
| [laravel/Http/ThrottledApiController.php](laravel/Http/ThrottledApiController.php) | Controller: `attempt()`→429, `withThrowOnLimit()`, `withMaxWait()` |
| [laravel/Console/FetchFeedsCommand.php](laravel/Console/FetchFeedsCommand.php) | Command loop; ad-hoc `for()`; `store('redis')->for()` driver selection |
| [laravel/Providers/LimiterRegistrationProvider.php](laravel/Providers/LimiterRegistrationProvider.php) | Registering/`redefine`-ing limiters in code; resolving from the container |
