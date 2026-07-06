<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Store;

use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\Gcra;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Reservation;

/**
 * File-based store. Coordinates processes that share a filesystem by holding an
 * exclusive advisory lock (flock) around the read-modify-write of each limiter's
 * state file. The lock is always released before the caller sleeps.
 *
 * Only reliable across processes on a single host / shared mount — for a
 * multi-host fleet use the Redis or database store instead.
 */
final class FileStore implements Store
{
    private readonly string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');

        if (! is_dir($this->directory)) {
            if (! @mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
                throw new \RuntimeException("Unable to create throttle directory: {$this->directory}");
            }
        }
    }

    public function reserve(string $key, RateLimit $limit, float $maxWaitSeconds, float $now): Reservation
    {
        $handle = fopen($this->pathFor($key), 'c+');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open throttle file for key: {$key}");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Unable to lock throttle file for key: {$key}");
            }

            $contents = trim((string) stream_get_contents($handle));
            $tat = is_numeric($contents) ? (float) $contents : null;

            [$allowed, $wait, $newTat] = Gcra::reserve($tat, $now, $limit, $maxWaitSeconds);

            if ($allowed) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, (string) $newTat);
                fflush($handle);
            }

            return new Reservation($allowed, $wait);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function provision(string $id, RateLimit $limit, bool $overwrite = false): RateLimit
    {
        $handle = fopen($this->configPathFor($id), 'c+');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open throttle config file for id: {$id}");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Unable to lock throttle config file for id: {$id}");
            }

            $contents = trim((string) stream_get_contents($handle));

            if (! $overwrite && $contents !== '') {
                return RateLimit::deserialize($contents);
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $limit->serialize());
            fflush($handle);

            return $limit;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function pathFor(string $key): string
    {
        return $this->directory.'/'.hash('sha256', $key).'.throttle';
    }

    private function configPathFor(string $id): string
    {
        return $this->directory.'/'.hash('sha256', $id).'.config';
    }
}
