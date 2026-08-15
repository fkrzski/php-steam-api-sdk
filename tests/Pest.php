<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\MockConfig;

MockConfig::throwOnMissingFixtures();

/**
 * @return list<SteamId>
 */
function makeSteamIds(int $count): array
{
    $base = 76561198000000000;

    return array_map(
        static fn (int $offset): SteamId => SteamId::fromSteamId64((string) ($base + $offset)),
        range(0, $count - 1),
    );
}
