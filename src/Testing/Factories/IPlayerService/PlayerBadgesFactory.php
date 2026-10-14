<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\PlayerBadges;

/**
 * Builds the `response` payload Steam returns for `GetBadges`.
 */
final readonly class PlayerBadgesFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'badges' => [BadgeFactory::new()->toArray()],
            'player_xp' => 1500,
            'player_level' => 12,
            'player_xp_needed_to_level_up' => 100,
            'player_xp_needed_current_level' => 1400,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function badges(BadgeFactory ...$badges): self
    {
        return $this->state([
            'badges' => array_map(
                static fn (BadgeFactory $badge): array => $badge->toArray(),
                $badges,
            ),
        ]);
    }

    public function level(int $level): self
    {
        return $this->state(['player_level' => $level]);
    }

    /**
     * Steam drops the key for an account that has earned no badges at all.
     */
    public function withoutBadges(): self
    {
        $attributes = $this->attributes;

        unset($attributes['badges']);

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): PlayerBadges
    {
        /**
         * @var array{
         *     badges?: list<array{
         *         badgeid: int,
         *         appid?: int,
         *         level: int,
         *         completion_time: int,
         *         xp: int,
         *         communityitemid?: string,
         *         border_color?: int,
         *         scarcity: int,
         *     }>,
         *     player_xp: int,
         *     player_level: int,
         *     player_xp_needed_to_level_up: int,
         *     player_xp_needed_current_level: int,
         * } $payload
         */
        $payload = $this->attributes;

        return PlayerBadges::fromArray($payload);
    }
}
