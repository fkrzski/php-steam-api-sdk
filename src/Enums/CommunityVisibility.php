<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

use UnexpectedValueException;

enum CommunityVisibility
{
    case Hidden;
    case Visible;

    public static function fromApiValue(int $value): self
    {
        return match ($value) {
            1 => self::Hidden,
            3 => self::Visible,
            default => throw new UnexpectedValueException(sprintf('Unknown communityvisibilitystate value "%d".', $value)),
        };
    }
}
