<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

enum ServerRegion: int
{
    case World = -1;
    case UsEast = 0;
    case UsWest = 1;
    case SouthAmerica = 2;
    case Europe = 3;
    case Asia = 4;
    case Australia = 5;
    case MiddleEast = 6;
    case Africa = 7;

    /**
     * Valve's master server protocol spells the world 255, so anything unlisted lands there too.
     */
    public static function fromApiValue(int $value): self
    {
        return self::tryFrom($value) ?? self::World;
    }
}
