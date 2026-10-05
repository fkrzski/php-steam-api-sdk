<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class SteamUserNotFoundException extends SteamApiException
{
    public static function forVanity(string $vanityName, Response $response): self
    {
        return new self(sprintf('No Steam user found for vanity name "%s".', $vanityName), $response);
    }
}
