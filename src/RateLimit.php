<?php

declare(strict_types=1);

namespace ZeroxBliv\CallThrottle;

/**
 * An immutable "count per period" rate, e.g. 4 per second.
 *
 * The limiter permits an initial burst of up to `count` calls, then paces
 * subsequent calls one every `emissionInterval()` seconds.
 */
final readonly class RateLimit
{
    public function __construct(
        public int $count,
        public float $periodSeconds,
    ) {
        if ($count < 1) {
            throw new \InvalidArgumentException('RateLimit count must be >= 1.');
        }

        if ($periodSeconds <= 0) {
            throw new \InvalidArgumentException('RateLimit period must be > 0 seconds.');
        }
    }

    public static function of(int $count, float $periodSeconds): self
    {
        return new self($count, $periodSeconds);
    }

    public static function perSecond(int $count): self
    {
        return new self($count, 1.0);
    }

    public static function perMinute(int $count): self
    {
        return new self($count, 60.0);
    }

    public static function perHour(int $count): self
    {
        return new self($count, 3600.0);
    }

    /**
     * Parse a rate string like "100/minute", "4/second" or "100/60" (seconds).
     */
    public static function fromString(string $spec): self
    {
        $parts = explode('/', $spec);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid rate '{$spec}'. Use e.g. '100/minute' or '4/second'.");
        }

        return new self((int) trim($parts[0]), self::periodToSeconds(trim($parts[1])));
    }

    /**
     * Resolve a period keyword ('second', 'minute', 'hour', 'day') or a number
     * of seconds to seconds.
     */
    public static function periodToSeconds(string|int|float $period): float
    {
        if (is_int($period) || is_float($period)) {
            return (float) $period;
        }

        if (is_numeric($period)) {
            return (float) $period;
        }

        return match (strtolower($period)) {
            'second', 'sec', 's' => 1.0,
            'minute', 'min', 'm' => 60.0,
            'hour', 'h' => 3600.0,
            'day', 'd' => 86400.0,
            default => throw new \InvalidArgumentException("Unknown period '{$period}'. Use a keyword or a number of seconds."),
        };
    }

    /**
     * Seconds between two paced calls once the burst allowance is used up.
     */
    public function emissionInterval(): float
    {
        return $this->periodSeconds / $this->count;
    }

    public function equals(self $other): bool
    {
        return $this->count === $other->count
            && abs($this->periodSeconds - $other->periodSeconds) < 1e-9;
    }

    /**
     * Compact form for persisting the definition in a store: "count|periodSeconds".
     */
    public function serialize(): string
    {
        return $this->count.'|'.$this->periodSeconds;
    }

    public static function deserialize(string $value): self
    {
        $parts = explode('|', $value);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Malformed serialized RateLimit '{$value}'.");
        }

        return new self((int) $parts[0], (float) $parts[1]);
    }
}
