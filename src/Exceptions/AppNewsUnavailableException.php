<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class AppNewsUnavailableException extends SteamApiException
{
    public static function forAppId(int $appId, Response $response): self
    {
        return new self(
            sprintf('Steam returned no news for app %d: no app has that ID, or Steam does not publish its news.', $appId),
            $response,
        );
    }
}
