<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievement;

/**
 * Builds the payload Steam returns for one entry of `GetPlayerAchievements`.
 *
 * `name` and `description` are omitted by default — Steam only sends them when
 * the request carries a language, see {@see self::withDetails()}.
 */
final readonly class PlayerAchievementFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'apiname' => 'ACH_UNLOCK_KILLER_CHARACTER',
            'achieved' => 1,
            'unlocktime' => 1600000000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function apiName(string $apiName): self
    {
        return $this->state(['apiname' => $apiName]);
    }

    public function unlocked(DateTimeInterface $unlockedAt): self
    {
        return $this->state([
            'achieved' => 1,
            'unlocktime' => $unlockedAt->getTimestamp(),
        ]);
    }

    /**
     * A locked achievement carries `unlocktime` 0, which the DTO reads as null.
     */
    public function locked(): self
    {
        return $this->state([
            'achieved' => 0,
            'unlocktime' => 0,
        ]);
    }

    public function withDetails(
        string $name = 'Left for Dead',
        string $description = 'Unlock a killer character.',
    ): self {
        return $this->state([
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): PlayerAchievement
    {
        /**
         * @var array{
         *     apiname: string,
         *     achieved: int,
         *     unlocktime: int,
         *     name?: string,
         *     description?: string,
         * } $payload
         */
        $payload = $this->attributes;

        return PlayerAchievement::fromArray($payload);
    }
}
