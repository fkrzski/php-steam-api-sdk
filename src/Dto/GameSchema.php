<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class GameSchema
{
    /**
     * @param  list<SchemaStat>  $stats
     * @param  list<SchemaAchievement>  $achievements
     */
    public function __construct(
        public ?string $gameName,
        public ?string $gameVersion,
        public array $stats,
        public array $achievements,
    ) {}

    /**
     * Steam drops every key for an app that publishes no schema, and sends `gameName`
     * as an empty string for some that do, so an absent name and a blank one are the
     * same thing here.
     *
     * @param  array{
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
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        $gameName = $payload['gameName'] ?? null;
        $availableGameStats = $payload['availableGameStats'] ?? [];

        return new self(
            gameName: $gameName !== '' ? $gameName : null,
            gameVersion: $payload['gameVersion'] ?? null,
            stats: array_map(SchemaStat::fromArray(...), $availableGameStats['stats'] ?? []),
            achievements: array_map(SchemaAchievement::fromArray(...), $availableGameStats['achievements'] ?? []),
        );
    }
}
