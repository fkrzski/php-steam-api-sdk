<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Dto\Badge;

/**
 * Builds the payload Steam returns for one entry of `GetBadges`.
 *
 * Defaults describe a badge earned in a game. The two shapes worth naming are
 * the community item id, which Steam sends as a string, and a badge tied to no
 * app — see {@see self::communityItem()} and {@see self::withoutApp()}.
 */
final readonly class BadgeFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'badgeid' => 13,
            'appid' => 381210,
            'level' => 5,
            'completion_time' => 1600000000,
            'xp' => 500,
            'border_color' => 0,
            'scarcity' => 12345,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function badgeId(int $badgeId): self
    {
        return $this->state(['badgeid' => $badgeId]);
    }

    public function appId(int $appId): self
    {
        return $this->state(['appid' => $appId]);
    }

    public function level(int $level): self
    {
        return $this->state(['level' => $level]);
    }

    public function completedAt(DateTimeInterface $completedAt): self
    {
        return $this->state(['completion_time' => $completedAt->getTimestamp()]);
    }

    /**
     * Steam sends this one as a string; it outgrows what a 32-bit int holds.
     */
    public function communityItem(string $communityItemId = '2101234567890123456'): self
    {
        return $this->state(['communityitemid' => $communityItemId]);
    }

    /**
     * A badge earned outside any game — Steam drops the app id along with the
     * border colour that goes with it.
     */
    public function withoutApp(): self
    {
        $attributes = $this->attributes;

        unset($attributes['appid'], $attributes['border_color']);

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): Badge
    {
        /**
         * @var array{
         *     badgeid: int,
         *     appid?: int,
         *     level: int,
         *     completion_time: int,
         *     xp: int,
         *     communityitemid?: string,
         *     border_color?: int,
         *     scarcity: int,
         * } $payload
         */
        $payload = $this->attributes;

        return Badge::fromArray($payload);
    }
}
