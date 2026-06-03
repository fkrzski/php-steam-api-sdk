<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\RateLimitPlugin\Limit;

final class SteamRateLimitException extends SteamApiException
{
    private function __construct(string $message, public readonly Limit $limit)
    {
        parent::__construct($message);
    }

    public static function fromLimit(Limit $limit): self
    {
        return new self(
            sprintf('Steam API rate limit reached (limit: %s).', $limit->getName()),
            $limit,
        );
    }
}
