<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\OwnedGame;

/**
 * Builds the payload Steam returns for one entry of `GetOwnedGames`.
 *
 * Defaults omit `name`, `img_icon_url` and `has_community_visible_stats`, which
 * Steam only sends when the request asks for app info — see {@see self::withAppInfo()}.
 */
final readonly class OwnedGameFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'appid' => 381210,
            'playtime_forever' => 1200,
            'playtime_2weeks' => 60,
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

    public function withAppInfo(string $name = 'Dead by Daylight'): self
    {
        return $this->state([
            'name' => $name,
            'img_icon_url' => 'ee6b1c0d1e3f2a4b5c6d7e8f9a0b1c2d3e4f5a6b',
            'has_community_visible_stats' => true,
        ]);
    }

    public function neverPlayed(): self
    {
        $attributes = $this->attributes;

        unset($attributes['playtime_2weeks']);

        $attributes['playtime_forever'] = 0;

        return new self($attributes);
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

    public function make(): OwnedGame
    {
        /**
         * @var array{
         *     appid: int,
         *     playtime_forever: int,
         *     playtime_2weeks?: int,
         *     name?: string,
         *     img_icon_url?: string,
         *     has_community_visible_stats?: bool,
         * } $payload
         */
        $payload = $this->attributes;

        return OwnedGame::fromArray($payload);
    }
}
