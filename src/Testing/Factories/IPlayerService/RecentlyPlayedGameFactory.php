<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGame;

/**
 * Builds the payload Steam returns for one entry of `GetRecentlyPlayedGames`.
 *
 * Unlike `GetOwnedGames`, every key is always sent: the endpoint only lists games
 * played in the last two weeks, always with the name, the icon and the per-platform
 * breakdown.
 */
final readonly class RecentlyPlayedGameFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'appid' => 381210,
            'name' => 'Dead by Daylight',
            'playtime_2weeks' => 60,
            'playtime_forever' => 1200,
            'img_icon_url' => 'ee6b1c0d1e3f2a4b5c6d7e8f9a0b1c2d3e4f5a6b',
            'playtime_windows_forever' => 900,
            'playtime_mac_forever' => 0,
            'playtime_linux_forever' => 100,
            'playtime_deck_forever' => 200,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function appId(int $appId): self
    {
        return $this->state(['appid' => $appId]);
    }

    public function name(string $name): self
    {
        return $this->state(['name' => $name]);
    }

    public function playedRecently(int $minutes): self
    {
        return $this->state(['playtime_2weeks' => $minutes]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): RecentlyPlayedGame
    {
        /**
         * @var array{
         *     appid: int,
         *     name: string,
         *     playtime_2weeks: int,
         *     playtime_forever: int,
         *     img_icon_url: string,
         *     playtime_windows_forever: int,
         *     playtime_mac_forever: int,
         *     playtime_linux_forever: int,
         *     playtime_deck_forever: int,
         * } $payload
         */
        $payload = $this->attributes;

        return RecentlyPlayedGame::fromArray($payload);
    }
}
