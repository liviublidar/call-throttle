<?php

declare(strict_types=1);

/*
 * Shared limiter across separate processes.
 *
 * Run this in several terminals at once:
 *
 *     php examples/php/shared_workers/worker.php one
 *     php examples/php/shared_workers/worker.php two
 *     php examples/php/shared_workers/worker.php three
 *
 * Each process builds its own registry over the SAME file store and registers
 * the SAME id, so they share one 5-per-second budget. Across all terminals you
 * will never see more than 5 lines in any one second, regardless of how many
 * workers run or which one started first.
 */

require __DIR__.'/../../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\LimiterRegistry;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Store\FileStore;

$label = $argv[1] ?? (string) getmypid();

// Registration happens once, at "boot": provision the rate to the shared store
// (or adopt it if another worker already did). Reference by name thereafter.
$registry = new LimiterRegistry(new FileStore(sys_get_temp_dir().'/call-throttle-shared'));
$registry->register('external-api', RateLimit::perSecond(5));

for ($i = 1; $i <= 15; $i++) {
    $line = $registry->limiter('external-api')->run(static function () use ($label, $i): string {
        $t = microtime(true);

        return sprintf('%s.%03d  worker=%s  call #%d',
            date('H:i:s', (int) $t), (int) (($t - floor($t)) * 1000), $label, $i);
    });

    echo $line, PHP_EOL;
}
