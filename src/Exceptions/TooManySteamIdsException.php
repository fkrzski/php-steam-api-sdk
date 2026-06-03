<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

final class TooManySteamIdsException extends SteamApiException
{
    public static function forCount(int $count): self
    {
        return new self(sprintf('GetPlayerSummaries accepts up to 100 SteamIDs, %d given.', $count));
    }
}
