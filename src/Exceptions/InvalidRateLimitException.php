<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class InvalidRateLimitException extends SteamApiException
{
    /** @internal */
    public static function duplicateName(int|string $first, int|string $second, string $name): self
    {
        return new self(sprintf(
            'SteamConfig::$rateLimits[%s] and [%s] are both named "%s". Give one of them a name of its own with ->name().',
            $first,
            $second,
            $name,
        ));
    }

    /** @internal */
    public static function reservedName(int|string $key, string $name): self
    {
        return new self(sprintf(
            'SteamConfig::$rateLimits[%s] is named "%s", which the connector keeps for the lock after a 429. Give it a name of its own with ->name().',
            $key,
            $name,
        ));
    }

    /** @internal */
    public static function tooShortCooldown(int $seconds): self
    {
        return new self(sprintf(
            'SteamConfig::$tooManyRequestsCooldown must be at least 1, got %d. Pass the seconds to hold requests back after a 429 that carries no Retry-After.',
            $seconds,
        ));
    }
}
