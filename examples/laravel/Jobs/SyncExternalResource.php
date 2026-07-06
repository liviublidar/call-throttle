<?php

declare(strict_types=1);

/*
 * Queue job: the canonical "many workers, one shared API limit" case.
 *
 * 'external-api' is defined once in config/call-throttle.php and registered at
 * boot on every worker. Dispatch as many of these as you like; they pace
 * themselves to the shared budget across all workers and servers:
 *
 *     foreach ($ids as $id) {
 *         SyncExternalResource::dispatch($id);
 *     }
 */

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use ZeroxBliv\CallThrottle\Laravel\Facades\CallThrottle;

final class SyncExternalResource implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(private readonly string $resourceId)
    {
    }

    public function handle(): void
    {
        // Reference by name — no rate here. withMaxWait() tunes only THIS caller's
        // wait policy (not the shared rate). run() returns the Http response, so it
        // reads just like a direct Http::get() call.
        $response = CallThrottle::limiter('external-api')
            ->withMaxWait(30) // wait up to 30s for a slot, else it throws and the job retries
            ->run(fn () => Http::get("https://api.example.com/resources/{$this->resourceId}"));

        $response->throw();

        // ... persist $response->json() ...
    }
}
