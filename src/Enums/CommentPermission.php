<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

use UnexpectedValueException;

enum CommentPermission
{
    case Everyone;
    case Nobody;
    case FriendsOnly;

    public static function fromApiValue(?int $value): self
    {
        return match ($value) {
            1 => self::Everyone,
            2 => self::Nobody,
            null => self::FriendsOnly,
            default => throw new UnexpectedValueException(sprintf('Unknown commentpermission value "%d".', $value)),
        };
    }
}
