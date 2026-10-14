<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\GameSchema;

/**
 * Builds the `game` payload Steam returns for `GetSchemaForGame`.
 */
final readonly class GameSchemaFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'gameName' => 'Dead by Daylight',
            'gameVersion' => '17',
            'availableGameStats' => [
                'stats' => [SchemaStatFactory::new()->toArray()],
                'achievements' => [SchemaAchievementFactory::new()->toArray()],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function gameName(string $gameName): self
    {
        return $this->state(['gameName' => $gameName]);
    }

    public function gameVersion(string $gameVersion): self
    {
        return $this->state(['gameVersion' => $gameVersion]);
    }

    public function stats(SchemaStatFactory ...$stats): self
    {
        return $this->availableGameStats([
            'stats' => array_map(
                static fn (SchemaStatFactory $stat): array => $stat->toArray(),
                $stats,
            ),
        ]);
    }

    public function achievements(SchemaAchievementFactory ...$achievements): self
    {
        return $this->availableGameStats([
            'achievements' => array_map(
                static fn (SchemaAchievementFactory $achievement): array => $achievement->toArray(),
                $achievements,
            ),
        ]);
    }

    /**
     * Some games publish a schema carrying no name, which Steam sends as an empty
     * string rather than omitting.
     */
    public function unnamed(): self
    {
        return $this->state(['gameName' => '']);
    }

    /**
     * Steam drops every key for an app that publishes no schema, the name and the
     * version included, so this leaves nothing behind.
     */
    public function empty(): self
    {
        return new self([]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): GameSchema
    {
        /**
         * @var array{
         *     gameName?: string,
         *     gameVersion?: string,
         *     availableGameStats?: array{
         *         stats?: list<array{name: string, defaultvalue: int|float, displayName: string}>,
         *         achievements?: list<array{
         *             name: string,
         *             displayName: string,
         *             hidden: int,
         *             description?: string,
         *             icon: string,
         *             icongray: string,
         *         }>,
         *     },
         * } $payload
         */
        $payload = $this->attributes;

        return GameSchema::fromArray($payload);
    }

    /**
     * Both lists sit under one key, so setting either has to keep what the other
     * left rather than replace the whole branch.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function availableGameStats(array $overrides): self
    {
        /** @var array<string, mixed> $current */
        $current = $this->attributes['availableGameStats'] ?? [];

        return $this->state(['availableGameStats' => [...$current, ...$overrides]]);
    }
}
