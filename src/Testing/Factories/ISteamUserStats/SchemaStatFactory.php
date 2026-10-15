<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\SchemaStat;

/**
 * Builds the payload Steam returns for one entry of
 * `game.availableGameStats.stats` on `GetSchemaForGame`.
 */
final readonly class SchemaStatFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'DBD_KillerSkulls',
            'defaultvalue' => 0,
            'displayName' => 'Killer Skulls',
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

    public function defaultValue(int|float $defaultValue): self
    {
        return $this->state(['defaultvalue' => $defaultValue]);
    }

    /**
     * A stat the game publishes without a display name, which Steam sends as an
     * empty string rather than omitting.
     */
    public function unnamed(): self
    {
        return $this->state(['displayName' => '']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): SchemaStat
    {
        /** @var array{name: string, defaultvalue: int|float, displayName: string} $payload */
        $payload = $this->attributes;

        return SchemaStat::fromArray($payload);
    }
}
