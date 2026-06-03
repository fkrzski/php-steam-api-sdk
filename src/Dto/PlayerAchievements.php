<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

final readonly class PlayerAchievements
{
    /**
     * @param  list<PlayerAchievement>  $achievements
     */
    public function __construct(
        public SteamId $steamId,
        public string $gameName,
        public array $achievements,
    ) {}

    /**
     * @param  array{
     *     steamID: string,
     *     gameName: string,
     *     achievements: list<array{apiname: string, achieved: int, unlocktime: int, name?: string, description?: string}>,
     *     success: bool,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            steamId: SteamId::fromSteamId64($payload['steamID']),
            gameName: $payload['gameName'],
            achievements: array_map(PlayerAchievement::fromArray(...), $payload['achievements']),
        );
    }
}
