<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Store;

use ZeroxBliv\CallThrottle\Contracts\Store;
use ZeroxBliv\CallThrottle\Gcra;
use ZeroxBliv\CallThrottle\RateLimit;
use ZeroxBliv\CallThrottle\Reservation;

/**
 * PDO-backed store. Each limiter is one row keyed by its id; the reserve is made
 * atomic by performing the read-modify-write inside a transaction, taking a row
 * lock (SELECT ... FOR UPDATE) on MySQL/Postgres.
 *
 * Note: SQLite serialises writers with a database-level lock; heavy concurrent
 * writers may hit SQLITE_BUSY. Prefer Redis for high-contention distributed use.
 */
final class DatabaseStore implements Store
{
    private readonly string $driver;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $stateTable = 'call_throttle_limiter_state',
        private readonly string $configTable = 'call_throttle_limiters',
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->driver = (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function reserve(string $key, RateLimit $limit, float $maxWaitSeconds, float $now): Reservation
    {
        $lock = in_array($this->driver, ['mysql', 'pgsql'], true) ? ' FOR UPDATE' : '';

        $this->pdo->beginTransaction();

        try {
            $select = $this->pdo->prepare("SELECT tat FROM {$this->stateTable} WHERE limiter_id = ?{$lock}");
            $select->execute([$key]);
            $value = $select->fetchColumn();
            $tat = ($value === false || $value === null) ? null : (float) $value;

            [$allowed, $wait, $newTat] = Gcra::reserve($tat, $now, $limit, $maxWaitSeconds);

            if ($allowed) {
                $expiresAt = (int) ceil($now + $limit->periodSeconds + $limit->emissionInterval()) + 1;
                $upsert = $this->pdo->prepare($this->upsertSql());
                $upsert->execute([$key, $newTat, $expiresAt]);
            }

            $this->pdo->commit();

            return new Reservation($allowed, $wait);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function provision(string $id, RateLimit $limit, bool $overwrite = false): RateLimit
    {
        if ($overwrite) {
            $sql = match ($this->driver) {
                'mysql' => "REPLACE INTO {$this->configTable} (limiter_id, max_calls, period_seconds) VALUES (?, ?, ?)",
                default => "INSERT INTO {$this->configTable} (limiter_id, max_calls, period_seconds) VALUES (?, ?, ?) "
                    .'ON CONFLICT(limiter_id) DO UPDATE SET max_calls = excluded.max_calls, period_seconds = excluded.period_seconds',
            };
            $this->pdo->prepare($sql)->execute([$id, $limit->count, $limit->periodSeconds]);

            return $limit;
        }

        // Write the definition only if the id has none yet, then read back the winner.
        $insertIfAbsent = match ($this->driver) {
            'mysql' => "INSERT IGNORE INTO {$this->configTable} (limiter_id, max_calls, period_seconds) VALUES (?, ?, ?)",
            default => "INSERT INTO {$this->configTable} (limiter_id, max_calls, period_seconds) VALUES (?, ?, ?) "
                .'ON CONFLICT(limiter_id) DO NOTHING',
        };
        $this->pdo->prepare($insertIfAbsent)->execute([$id, $limit->count, $limit->periodSeconds]);

        $select = $this->pdo->prepare("SELECT max_calls, period_seconds FROM {$this->configTable} WHERE limiter_id = ?");
        $select->execute([$id]);
        $row = $select->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return $limit;
        }

        return new RateLimit((int) $row['max_calls'], (float) $row['period_seconds']);
    }

    /**
     * Create the limiter state and definition tables if they do not exist.
     * Convenience for non-Laravel usage; Laravel apps should publish and run the
     * shipped migration instead.
     */
    public function createSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$this->stateTable} ("
            .'limiter_id VARCHAR(191) PRIMARY KEY, '
            .'tat DOUBLE PRECISION NOT NULL, '
            .'expires_at BIGINT NOT NULL)'
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$this->configTable} ("
            .'limiter_id VARCHAR(191) PRIMARY KEY, '
            .'max_calls INTEGER NOT NULL, '
            .'period_seconds DOUBLE PRECISION NOT NULL)'
        );
    }

    private function upsertSql(): string
    {
        return match ($this->driver) {
            'mysql' => "INSERT INTO {$this->stateTable} (limiter_id, tat, expires_at) VALUES (?, ?, ?) "
                .'ON DUPLICATE KEY UPDATE tat = VALUES(tat), expires_at = VALUES(expires_at)',
            default => "INSERT INTO {$this->stateTable} (limiter_id, tat, expires_at) VALUES (?, ?, ?) "
                .'ON CONFLICT(limiter_id) DO UPDATE SET tat = excluded.tat, expires_at = excluded.expires_at',
        };
    }
}
