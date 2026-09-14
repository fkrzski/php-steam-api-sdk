<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Request;

final class ApiKeyNotConfiguredException extends SteamApiException
{
    public static function blank(): self
    {
        return new self('Steam API key is blank. Pass a real key to SteamConfig, or null to reach only the endpoints Steam serves anonymously.');
    }

    public static function forRequest(Request $request): self
    {
        return new self(sprintf(
            'No Steam API key is configured, and %s needs one. Pass a key to SteamConfig.',
            $request::class,
        ));
    }
}
