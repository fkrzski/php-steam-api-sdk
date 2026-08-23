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
     * Steam answers HTTP 200 with an empty `response` object both for a hidden profile
     * and for a SteamID64 that belongs to no account, so the cause cannot be recovered.
     */
    public static function forPrivateOrMissing(SteamId $steamId, ?Response $response = null): self
    {
        return new self(
            sprintf('Steam returned no data for profile %s: it is not public, or it does not exist.', $steamId),
            $response,
        );
    }

    /**
     * Used where the profile is not known, such as status mapping on the connector.
     */
    public static function fromResponse(Response $response): self
    {
        return new self('Steam profile is not public.', $response);
    }
}
