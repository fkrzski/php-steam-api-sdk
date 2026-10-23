<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class UnreadableResponseException extends SteamApiException
{
    /** @internal */
    public static function notJson(Response $response): self
    {
        return new self(
            sprintf('Steam API answered HTTP %d with a body that is not JSON.', $response->status()),
            $response,
        );
    }

    /** @internal */
    public static function missing(Response $response, string $path): self
    {
        return new self(
            sprintf('Steam API answered HTTP %d without %s.', $response->status(), $path),
            $response,
        );
    }
}
