<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class InvalidRateLimitException extends SteamApiException
{
    private function __construct(
        string $message,
        /** @since 1.1.0 */
        public readonly string $parameter,
        /** @since 1.1.0 */
        public readonly int|string|null $key = null,
        /** @since 1.1.0 */
        public readonly int|string|null $earlierKey = null,
    ) {
        parent::__construct($message);
    }

    /** @internal */
    public static function duplicateName(int|string $first, int|string $second, string $name): self
    {
        return new self(sprintf(
            'SteamConfig::$rateLimits[%s] and [%s] are both named "%s". Give one of them a name of its own with ->name().',
            $first,
            $second,
            $name,
        ), 'rateLimits', $second, $first);
    }

    /** @internal */
    public static function reservedName(int|string $key, string $name): self
    {
        return new self(sprintf(
            'SteamConfig::$rateLimits[%s] is named "%s", which the connector keeps for the lock after a 429. Give it a name of its own with ->name().',
            $key,
            $name,
        ), 'rateLimits', $key);
    }

    /** @internal */
    public static function tooShortCooldown(int $seconds): self
    {
        return new self(sprintf(
            'SteamConfig::$tooManyRequestsCooldown must be at least 1, got %d. Pass the seconds to hold requests back after a 429 that carries no Retry-After.',
            $seconds,
        ), 'tooManyRequestsCooldown');
    }
}
