<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;

/**
 * Builds the `response` payload Steam returns for `GetRecentlyPlayedGames`.
 */
final readonly class RecentlyPlayedGamesFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'total_count' => 1,
            'games' => [RecentlyPlayedGameFactory::new()->toArray()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    /**
     * Syncs `total_count` too, so a test that wants the two apart calls
     * {@see self::totalCount()} after this.
     */
    public function games(RecentlyPlayedGameFactory ...$games): self
    {
        return $this->state([
            'total_count' => count($games),
            'games' => array_map(
                static fn (RecentlyPlayedGameFactory $game): array => $game->toArray(),
                $games,
            ),
        ]);
    }

    /**
     * Steam counts every game played in the window, while `count` caps how many it
     * lists, so the total can outrun the payload.
     */
    public function totalCount(int $totalCount): self
    {
        return $this->state(['total_count' => $totalCount]);
    }

    /**
     * Steam drops the `games` key entirely for a player who played nothing.
     */
    public function nothingPlayed(): self
    {
        $attributes = $this->attributes;

        unset($attributes['games']);

        $attributes['total_count'] = 0;

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): RecentlyPlayedGames
    {
        /**
         * @var array{
         *     total_count: int,
         *     games?: list<array{
         *         appid: int,
         *         name: string,
         *         playtime_2weeks: int,
         *         playtime_forever: int,
         *         img_icon_url: string,
         *         playtime_windows_forever: int,
         *         playtime_mac_forever: int,
         *         playtime_linux_forever: int,
         *         playtime_deck_forever: int,
         *     }>,
         * } $payload
         */
        $payload = $this->attributes;

        return RecentlyPlayedGames::fromArray($payload);
    }
}
