<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

enum CommunityVisibility: int
{
    case Hidden = 1;
    case FriendsOnly = 2;
    case Visible = 3;
    case Unknown = -1;

    public static function fromApiValue(int $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
