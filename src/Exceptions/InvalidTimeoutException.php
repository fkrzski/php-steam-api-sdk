<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class InvalidTimeoutException extends SteamApiException
{
    public static function negative(string $option, float $value): self
    {
        return new self(sprintf(
            'SteamConfig::$%s cannot be negative, got %s. Pass seconds, 0 for no limit, or null for the default.',
            $option,
            $value,
        ));
    }
}
