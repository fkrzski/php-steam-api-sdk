<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

enum CommentPermission: string
{
    case Everyone = 'everyone';
    case Nobody = 'nobody';
    case FriendsOnly = 'friends_only';
    /** The SDK's fallback for a value Steam sends that this enum does not list. */
    case Unknown = 'unknown';

    public static function fromApiValue(?int $value): self
    {
        return match ($value) {
            1 => self::Everyone,
            2 => self::Nobody,
            null => self::FriendsOnly,
            default => self::Unknown,
        };
    }
}
