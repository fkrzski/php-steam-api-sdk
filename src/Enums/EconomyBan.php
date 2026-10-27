<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Enums;

enum EconomyBan: string
{
    case None = 'none';
    case Probation = 'probation';
    case Banned = 'banned';
    /** The SDK's fallback for a value Steam sends that this enum does not list. */
    case Unknown = 'unknown';

    /**
     * Steam documents only "none" and "probation", then trails off with "and so
     * forth" — "banned" is observed on live accounts but undocumented
     */
    public static function fromApiValue(string $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
