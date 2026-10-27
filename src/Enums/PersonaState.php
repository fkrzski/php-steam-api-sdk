<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

enum PersonaState: int
{
    case Offline = 0;
    case Online = 1;
    case Busy = 2;
    case Away = 3;
    case Snooze = 4;
    case LookingToTrade = 5;
    case LookingToPlay = 6;
    case Invisible = 7;
    /** The SDK's fallback for a value Steam sends that this enum does not list. */
    case Unknown = -1;

    public static function fromApiValue(int $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
