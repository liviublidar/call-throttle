<?php

declare(strict_types=1);

/*
 * Artisan command: pace a batch loop, plus the ad-hoc (unshared) API and
 * explicit driver selection.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use ZeroxBliv\CallThrottle\Laravel\Facades\CallThrottle;

final class FetchFeedsCommand extends Command
{
    protected $signature = 'feeds:fetch';

    protected $description = 'Fetch every feed, paced against the shared external-api limit';

    public function handle(): int
    {
        $urls = ['https://api.example.com/a', 'https://api.example.com/b', 'https://api.example.com/c'];

        // Shared, registered limiter: blocks until this call's slot is free.
        foreach ($urls as $url) {
            $body = CallThrottle::limiter('external-api')->run(fn () => Http::get($url)->body());
            $this->line('fetched '.$url.' ('.strlen($body).' bytes)');
        }

        // Ad-hoc, unshared one-off limiter (rate inline; fine when nothing else
        // shares this id). Uses the default store.
        CallThrottle::for('feeds:one-off-cleanup')
            ->allow(1)->per('second')
            ->run(fn () => Http::delete('https://api.example.com/tmp'));

        // Force a specific configured driver for a one-off:
        CallThrottle::store('redis')
            ->for('feeds:redis-only')
            ->allow(2)->per('second')
            ->attempt(fn () => Http::get('https://api.example.com/ping'));

        return self::SUCCESS;
    }
}
