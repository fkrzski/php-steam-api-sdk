<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class AppVersionUnavailableException extends SteamApiException
{
    public static function forAppId(int $appId, Response $response): self
    {
        return new self(
            sprintf('Steam cannot check app %d for updates: no app has that ID, or it publishes no server version.', $appId),
            $response,
        );
    }
}
