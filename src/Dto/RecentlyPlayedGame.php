<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class RecentlyPlayedGame
{
    public function __construct(
        public int $appId,
        public string $name,
        public int $playtimeTwoWeeks,
        public int $playtimeForever,
        public string $imgIconUrl,
        public int $playtimeWindowsForever,
        public int $playtimeMacForever,
        public int $playtimeLinuxForever,
        public int $playtimeDeckForever,
    ) {}

    /**
     * @param  array{
     *     appid: int,
     *     name: string,
     *     playtime_2weeks: int,
     *     playtime_forever: int,
     *     img_icon_url: string,
     *     playtime_windows_forever: int,
     *     playtime_mac_forever: int,
     *     playtime_linux_forever: int,
     *     playtime_deck_forever: int,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            appId: $payload['appid'],
            name: $payload['name'],
            playtimeTwoWeeks: $payload['playtime_2weeks'],
            playtimeForever: $payload['playtime_forever'],
            imgIconUrl: $payload['img_icon_url'],
            playtimeWindowsForever: $payload['playtime_windows_forever'],
            playtimeMacForever: $payload['playtime_mac_forever'],
            playtimeLinuxForever: $payload['playtime_linux_forever'],
            playtimeDeckForever: $payload['playtime_deck_forever'],
        );
    }
}
