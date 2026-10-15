<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class InvalidApiKeyException extends SteamApiException
{
    /** @internal */
    public static function missing(Response $response): self
    {
        return new self('Steam received no API key. Check the key passed to SteamConfig.', $response);
    }

    /** @internal */
    public static function rejected(Response $response): self
    {
        return new self('Steam rejected the API key. Check that it is valid and active.', $response);
    }
}
