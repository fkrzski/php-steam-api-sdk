<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

/**
 * Builds the `playerstats` payload Steam returns for `GetPlayerAchievements`.
 */
final readonly class PlayerAchievementsFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'steamID' => '76561198000000000',
            'gameName' => 'Dead by Daylight',
            'success' => true,
            'achievements' => [PlayerAchievementFactory::new()->toArray()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function steamId(SteamId $steamId): self
    {
        return $this->state(['steamID' => $steamId->value]);
    }

    public function gameName(string $gameName): self
    {
        return $this->state(['gameName' => $gameName]);
    }

    public function achievements(PlayerAchievementFactory ...$achievements): self
    {
        return $this->state([
            'achievements' => array_map(
                static fn (PlayerAchievementFactory $achievement): array => $achievement->toArray(),
                $achievements,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): PlayerAchievements
    {
        /**
         * @var array{
         *     steamID: string,
         *     gameName: string,
         *     achievements: list<array{apiname: string, achieved: int, unlocktime: int, name?: string, description?: string}>,
         *     success: bool,
         * } $payload
         */
        $payload = $this->attributes;

        return PlayerAchievements::fromArray($payload);
    }
}
