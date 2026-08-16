<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class InvalidApiKeyException extends SteamApiException
{
    public static function missing(Response $response): self
    {
        return new self('Steam API key is missing. Check the key passed to SteamConfig.', $response);
    }

    public static function rejected(Response $response): self
    {
        return new self('Steam rejected the API key. Check that it is valid and active.', $response);
    }
}
