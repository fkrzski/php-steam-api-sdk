<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class InvalidRetryException extends SteamApiException
{
    public static function tooFewTries(int $tries): self
    {
        return new self(sprintf(
            'SteamConfig::$tries must be at least 1, got %d. Pass the total number of attempts, 1 to never retry.',
            $tries,
        ));
    }

    public static function negativeInterval(int $retryInterval): self
    {
        return new self(sprintf(
            'SteamConfig::$retryInterval cannot be negative, got %d. Pass milliseconds, 0 for no pause between attempts.',
            $retryInterval,
        ));
    }
}
