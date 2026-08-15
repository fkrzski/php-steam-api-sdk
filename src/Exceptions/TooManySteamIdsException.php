<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class TooManySteamIdsException extends SteamApiException
{
    public static function forCount(int $count, string $endpoint): self
    {
        return new self(sprintf('%s accepts up to 100 SteamIDs, %d given.', $endpoint, $count));
    }
}
