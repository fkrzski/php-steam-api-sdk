<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

use UnexpectedValueException;

enum CommunityVisibility: int
{
    case Hidden = 1;
    case Visible = 3;

    public static function fromApiValue(int $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedValueException(sprintf('Unknown communityvisibilitystate value "%d".', $value));
    }
}
