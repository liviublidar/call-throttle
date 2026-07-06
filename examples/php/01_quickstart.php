<?php

declare(strict_types=1);

/*
 * Build a throttle, wrap work in run(), get the callback's return value back.
 */

require __DIR__.'/../../vendor/autoload.php';

use ZeroxBliv\CallThrottle\Store\FileStore;
use ZeroxBliv\CallThrottle\Throttle;

$dir = sys_get_temp_dir().'/call-throttle-ex-quickstart';

// Configure once -> a frozen, reusable, id-bound throttle.
$throttle = Throttle::for('quickstart')
    ->allow(3)->per('second')          // 3 calls per second (burst 3, then paced)
    ->store(new FileStore($dir))
    ->build();

// run() returns exactly what the callback returns.
$greeting = $throttle->run(fn (): string => 'hello from the throttled call');
echo $greeting, PHP_EOL;

// Reuse the same instance. First 3 are instant, the rest are paced ~0.33s apart.
$start = microtime(true);
for ($i = 1; $i <= 6; $i++) {
    $throttle->run(fn () => printf("call %d at +%.3fs\n", $i, microtime(true) - $start));
}

// cleanup
array_map('unlink', glob($dir.'/*') ?: []);
@rmdir($dir);
