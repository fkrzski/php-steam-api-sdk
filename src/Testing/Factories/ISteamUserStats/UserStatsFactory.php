<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

/**
 * Builds the `playerstats` payload Steam returns for `GetUserStatsForGame`.
 */
final readonly class UserStatsFactory
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
            'stats' => [UserStatFactory::new()->toArray()],
            'achievements' => [UserStatAchievementFactory::new()->toArray()],
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

    public function stats(UserStatFactory ...$stats): self
    {
        return $this->state([
            'stats' => array_map(
                static fn (UserStatFactory $stat): array => $stat->toArray(),
                $stats,
            ),
        ]);
    }

    public function achievements(UserStatAchievementFactory ...$achievements): self
    {
        return $this->state([
            'achievements' => array_map(
                static fn (UserStatAchievementFactory $achievement): array => $achievement->toArray(),
                $achievements,
            ),
        ]);
    }

    /**
     * Steam drops both keys for a game the player has no recorded progress in.
     */
    public function empty(): self
    {
        $attributes = $this->attributes;

        unset($attributes['stats'], $attributes['achievements']);

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): UserStats
    {
        /**
         * @var array{
         *     steamID: string,
         *     gameName: string,
         *     stats?: list<array{name: string, value: int|float}>,
         *     achievements?: list<array{name: string, achieved: int}>,
         * } $payload
         */
        $payload = $this->attributes;

        return UserStats::fromArray($payload);
    }
}
