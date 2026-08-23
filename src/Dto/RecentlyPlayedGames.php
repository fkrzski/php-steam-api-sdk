<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class RecentlyPlayedGames
{
    /**
     * @param  list<RecentlyPlayedGame>  $games
     */
    public function __construct(
        public int $totalCount,
        public array $games,
    ) {}

    /**
     * @param  array{total_count: int, games?: list<array{
     *     appid: int,
     *     name: string,
     *     playtime_2weeks: int,
     *     playtime_forever: int,
     *     img_icon_url: string,
     *     playtime_windows_forever: int,
     *     playtime_mac_forever: int,
     *     playtime_linux_forever: int,
     *     playtime_deck_forever: int,
     * }>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            totalCount: $payload['total_count'],
            games: array_map(RecentlyPlayedGame::fromArray(...), $payload['games'] ?? []),
        );
    }
}
