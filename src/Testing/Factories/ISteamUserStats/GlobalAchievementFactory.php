<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\GlobalAchievement;

/**
 * Builds the payload Steam returns for one entry of
 * `GetGlobalAchievementPercentagesForApp`.
 *
 * Steam sends the percentage as a string, so the payload holds one too — the DTO
 * is what casts it to a float.
 */
final readonly class GlobalAchievementFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'ACH_UNLOCK_KILLER_CHARACTER',
            'percent' => '32.4',
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
        return $this->state(['name' => $apiName]);
    }

    public function percent(float $percent): self
    {
        return $this->state(['percent' => (string) $percent]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): GlobalAchievement
    {
        /** @var array{name: string, percent: string} $payload */
        $payload = $this->attributes;

        return GlobalAchievement::fromArray($payload);
    }
}
