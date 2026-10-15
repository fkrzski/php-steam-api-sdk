<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\UserStatAchievement;

/**
 * Builds the payload Steam returns for one entry of `playerstats.achievements`
 * on `GetUserStatsForGame` — a flatter shape than the `GetPlayerAchievements`
 * entry that {@see PlayerAchievementFactory} builds.
 */
final readonly class UserStatAchievementFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'ACH_UNLOCK_KILLER_CHARACTER',
            'achieved' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function name(string $name): self
    {
        return $this->state(['name' => $name]);
    }

    public function achieved(): self
    {
        return $this->state(['achieved' => 1]);
    }

    public function locked(): self
    {
        return $this->state(['achieved' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): UserStatAchievement
    {
        /** @var array{name: string, achieved: int} $payload */
        $payload = $this->attributes;

        return UserStatAchievement::fromArray($payload);
    }
}
