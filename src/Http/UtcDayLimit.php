<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http;

use DateTimeImmutable;
use DateTimeZone;
use Saloon\RateLimitPlugin\Limit;

/**
 * Requests a day, closing at 00:00 UTC on the counter of the default budget whatever the allowance.
 *
 * @since 1.1.0
 */
final class UtcDayLimit extends Limit
{
    public static function requests(int $allow): self
    {
        return self::allow($allow)->everySeconds(86_400)->name('utc_day');
    }

    // Taken when the window opens, so a limit held in the config outlives midnight; untilMidnightTonight() follows date.timezone.
    public function getExpiryTimestamp(): int
    {
        return $this->expiryTimestamp ??= new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp();
    }
}
