<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class InvalidRetryException extends SteamApiException
{
    private function __construct(
        string $message,
        /** @since 1.1.0 */
        public readonly string $parameter,
    ) {
        parent::__construct($message);
    }

    /** @internal */
    public static function tooFewTries(int $tries): self
    {
        return new self(sprintf(
            'SteamConfig::$tries must be at least 1, got %d. Pass the total number of attempts, 1 to never retry.',
            $tries,
        ), 'tries');
    }

    /** @internal */
    public static function negativeInterval(int $retryInterval): self
    {
        return new self(sprintf(
            'SteamConfig::$retryInterval cannot be negative, got %d. Pass milliseconds, 0 for no pause between attempts.',
            $retryInterval,
        ), 'retryInterval');
    }
}
