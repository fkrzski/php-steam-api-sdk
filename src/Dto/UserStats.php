<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

final readonly class UserStats
{
    /**
     * @param  list<UserStat>  $stats
     * @param  list<UserStatAchievement>  $achievements
     */
    public function __construct(
        public SteamId $steamId,
        public string $gameName,
        public array $stats,
        public array $achievements,
    ) {}

    /**
     * @param  array{
     *     steamID: string,
     *     gameName: string,
     *     stats?: list<array{name: string, value: int|float}>,
     *     achievements?: list<array{name: string, achieved: int}>,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            steamId: SteamId::fromSteamId64($payload['steamID']),
            gameName: $payload['gameName'],
            stats: array_map(UserStat::fromArray(...), $payload['stats'] ?? []),
            achievements: array_map(UserStatAchievement::fromArray(...), $payload['achievements'] ?? []),
        );
    }
}
