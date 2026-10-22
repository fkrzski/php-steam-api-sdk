<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Limit;

final class SteamRateLimitException extends SteamApiException
{
    private function __construct(string $message, public readonly Limit $limit, ?Response $response)
    {
        parent::__construct($message, $response);
    }

    /** @internal */
    public static function fromLimit(Limit $limit, ?Response $response = null): self
    {
        return new self(
            sprintf('Steam API rate limit reached (limit: %s).', $limit->getName()),
            $limit,
            $response,
        );
    }
}
