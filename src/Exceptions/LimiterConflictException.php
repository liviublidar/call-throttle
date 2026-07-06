<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Exceptions;

use ZeroxBliv\CallThrottle\RateLimit;

/**
 * Thrown when a limiter id is registered with a rate that disagrees with the
 * definition already stored in the shared backend.
 */
final class LimiterConflictException extends \RuntimeException
{
    public function __construct(
        public readonly string $limiterId,
        public readonly RateLimit $requested,
        public readonly RateLimit $existing,
    ) {
        parent::__construct(sprintf(
            'Limiter "%s" is already defined as %d per %ss in the shared store; '
            .'cannot re-register it as %d per %ss. Use redefine() to change it intentionally.',
            $limiterId,
            $existing->count,
            self::seconds($existing->periodSeconds),
            $requested->count,
            self::seconds($requested->periodSeconds),
        ));
    }

    private static function seconds(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3f', $value), '0'), '.');
    }
}
