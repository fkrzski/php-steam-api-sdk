<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

enum CommunityVisibility: int
{
    case Hidden = 1;
    case FriendsOnly = 2;
    case Visible = 3;
    /** The SDK's fallback for a value Steam sends that this enum does not list. */
    case Unknown = -1;

    public static function fromApiValue(int $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
