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
}
