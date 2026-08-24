<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use DateTimeImmutable;

final readonly class Badge
{
    public function __construct(
        public int $badgeId,
        public ?int $appId,
        public int $level,
        public DateTimeImmutable $completedAt,
        public int $xp,
        public ?string $communityItemId,
        public ?int $borderColor,
        public int $scarcity,
    ) {}

    /**
     * @param  array{
     *     badgeid: int,
     *     appid?: int,
     *     level: int,
     *     completion_time: int,
     *     xp: int,
     *     communityitemid?: string,
     *     border_color?: int,
     *     scarcity: int,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            badgeId: $payload['badgeid'],
            appId: $payload['appid'] ?? null,
            level: $payload['level'],
            completedAt: new DateTimeImmutable('@'.$payload['completion_time']),
            xp: $payload['xp'],
            // Steam sends this one as a string; it exceeds what a 32-bit int holds.
            communityItemId: $payload['communityitemid'] ?? null,
            borderColor: $payload['border_color'] ?? null,
            scarcity: $payload['scarcity'],
        );
    }
}
