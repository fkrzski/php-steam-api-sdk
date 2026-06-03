<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class OwnedGame
{
    public function __construct(
        public int $appId,
        public int $playtimeForever,
        public ?int $playtimeTwoWeeks,
        public ?string $name,
        public ?string $imgIconUrl,
        public bool $hasCommunityVisibleStats,
    ) {}

    /**
     * @param  array{
     *     appid: int,
     *     playtime_forever: int,
     *     playtime_2weeks?: int,
     *     name?: string,
     *     img_icon_url?: string,
     *     has_community_visible_stats?: bool,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            appId: $payload['appid'],
            playtimeForever: $payload['playtime_forever'],
            playtimeTwoWeeks: $payload['playtime_2weeks'] ?? null,
            name: $payload['name'] ?? null,
            imgIconUrl: $payload['img_icon_url'] ?? null,
            hasCommunityVisibleStats: $payload['has_community_visible_stats'] ?? false,
        );
    }
}
