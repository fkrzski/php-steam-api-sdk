<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class PlayerBadges
{
    /**
     * @param  list<Badge>  $badges
     */
    public function __construct(
        public array $badges,
        public int $playerXp,
        public int $playerLevel,
        public int $xpNeededToLevelUp,
        public int $xpNeededForCurrentLevel,
    ) {}

    /**
     * @param  array{
     *     badges?: list<array{
     *         badgeid: int,
     *         appid?: int,
     *         level: int,
     *         completion_time: int,
     *         xp: int,
     *         communityitemid?: string,
     *         border_color?: int,
     *         scarcity: int,
     *     }>,
     *     player_xp: int,
     *     player_level: int,
     *     player_xp_needed_to_level_up: int,
     *     player_xp_needed_current_level: int,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            badges: array_map(Badge::fromArray(...), $payload['badges'] ?? []),
            playerXp: $payload['player_xp'],
            playerLevel: $payload['player_level'],
            xpNeededToLevelUp: $payload['player_xp_needed_to_level_up'],
            xpNeededForCurrentLevel: $payload['player_xp_needed_current_level'],
        );
    }
}
