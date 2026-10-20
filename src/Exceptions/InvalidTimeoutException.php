<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class InvalidTimeoutException extends SteamApiException
{
    /** @internal */
    public static function notFinite(string $option, float $value): self
    {
        // PHP 8.5 warns when NAN is cast to a string.
        return new self(sprintf(
            'SteamConfig::$%s must be finite, got %s. %s',
            $option,
            is_nan($value) ? 'NAN' : $value,
            self::hint($option),
        ));
    }

    /** @internal */
    public static function negative(string $option, float $value): self
    {
        return new self(sprintf(
            'SteamConfig::$%s cannot be negative, got %s. %s',
            $option,
            $value,
            self::hint($option),
        ));
    }

    /** @internal */
    public static function underOneMillisecond(string $option, float $value): self
    {
        return new self(sprintf(
            'SteamConfig::$%s must be 0 or at least 0.001, got %s. %s',
            $option,
            $value,
            self::hint($option),
        ));
    }

    private static function hint(string $option): string
    {
        return $option === 'connectTimeout'
            ? 'Pass seconds, 0 for no limit on Guzzle 8 and 300 s on Guzzle 7, or null for the default.'
            : 'Pass seconds, 0 for no limit, or null for the default.';
    }
}
