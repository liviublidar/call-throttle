<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Exceptions;

/**
 * Thrown when a limiter is referenced by name before it has been registered.
 */
final class UnknownLimiterException extends \RuntimeException
{
    public function __construct(public readonly string $limiterId)
    {
        parent::__construct(sprintf(
            'Limiter "%s" is not registered. Register it once (e.g. at boot) before referencing it.',
            $limiterId,
        ));
    }
}
