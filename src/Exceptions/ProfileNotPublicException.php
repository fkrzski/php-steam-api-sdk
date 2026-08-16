<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Response;

final class ProfileNotPublicException extends SteamApiException
{
    public static function forSteamId(SteamId $steamId, ?Response $response = null): self
    {
        return new self(sprintf('Steam profile %s is not public.', $steamId), $response);
    }

    /**
     * Used where the profile is not known, such as status mapping on the connector.
     */
    public static function fromResponse(Response $response): self
    {
        return new self('Steam profile is not public.', $response);
    }
}
