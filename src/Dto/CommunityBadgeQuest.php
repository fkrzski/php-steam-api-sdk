<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class CommunityBadgeQuest
{
    public function __construct(
        public int $questId,
        public bool $completed,
    ) {}

    /**
     * @param  array{questid: int, completed: bool}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            questId: $payload['questid'],
            completed: $payload['completed'],
        );
    }
}
