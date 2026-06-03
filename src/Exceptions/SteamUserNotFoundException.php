<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class SteamUserNotFoundException extends SteamApiException
{
    public static function forVanity(string $vanityName): self
    {
        return new self(sprintf('No Steam user found for vanity name "%s".', $vanityName));
    }
}
