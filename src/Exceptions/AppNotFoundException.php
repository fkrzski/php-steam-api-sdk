<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class AppNotFoundException extends SteamApiException
{
    public static function forAppId(int $appId, Response $response): self
    {
        return new self(sprintf('No Steam app found for app ID %d.', $appId), $response);
    }
}
