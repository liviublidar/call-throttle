<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle\Exceptions;

use ZeroxBliv\CallThrottle\RateLimit;

final class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        public readonly string $limiterId,
        public readonly float $waitSeconds,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forLimiter(string $limiterId, RateLimit $limit, float $waitSeconds): self
    {
        return new self($limiterId, $waitSeconds, sprintf(
            'Rate limit exceeded for "%s" (%d per %ss); retry after %.3fs.',
            $limiterId,
            $limit->count,
            rtrim(rtrim(sprintf('%.3f', $limit->periodSeconds), '0'), '.'),
            $waitSeconds,
        ));
    }
}
