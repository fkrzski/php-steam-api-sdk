<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class StatsUnavailableException extends SteamApiException
{
    public static function forAppId(int $appId, Response $response): self
    {
        return new self(
            sprintf('Steam returned no stats for app %d: it exposes none, or the profile is private.', $appId),
            $response,
        );
    }
}
