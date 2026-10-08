<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class InvalidRateLimitException extends SteamApiException
{
    public static function duplicateName(int|string $first, int|string $second, string $name): self
    {
        return new self(sprintf(
            'SteamConfig::$rateLimits[%s] and [%s] are both named "%s". Give one of them a name of its own with ->name().',
            $first,
            $second,
            $name,
        ));
    }
}
