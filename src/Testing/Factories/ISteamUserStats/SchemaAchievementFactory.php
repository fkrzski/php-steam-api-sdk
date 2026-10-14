<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\SchemaAchievement;

/**
 * Builds the payload Steam returns for one entry of
 * `game.availableGameStats.achievements` on `GetSchemaForGame`.
 *
 * Steam sends `hidden` as an int, so the payload holds one too — the DTO is what
 * casts it to a bool.
 */
final readonly class SchemaAchievementFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'ACH_UNLOCK_KILLER_CHARACTER',
            'displayName' => 'Left for Dead',
            'hidden' => 0,
            'description' => 'Unlock a killer character.',
            'icon' => 'https://cdn.steamstatic.com/steamcommunity/public/images/apps/381210/ee6b1c.jpg',
            'icongray' => 'https://cdn.steamstatic.com/steamcommunity/public/images/apps/381210/ee6b1c_gray.jpg',
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

    public function name(string $name): self
    {
        return $this->state(['displayName' => $name]);
    }

    public function description(string $description): self
    {
        return $this->state(['description' => $description]);
    }

    public function icons(string $icon, string $iconGray): self
    {
        return $this->state([
            'icon' => $icon,
            'icongray' => $iconGray,
        ]);
    }

    public function hidden(): self
    {
        return $this->state(['hidden' => 1]);
    }

    public function visible(): self
    {
        return $this->state(['hidden' => 0]);
    }

    /**
     * Steam omits the key rather than sending it empty for an achievement the game
     * publishes without a description.
     */
    public function withoutDescription(): self
    {
        $attributes = $this->attributes;

        unset($attributes['description']);

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): SchemaAchievement
    {
        /**
         * @var array{
         *     name: string,
         *     displayName: string,
         *     hidden: int,
         *     description?: string,
         *     icon: string,
         *     icongray: string,
         * } $payload
         */
        $payload = $this->attributes;

        return SchemaAchievement::fromArray($payload);
    }
}
