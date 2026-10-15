<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class CommunityBadgeQuest
{
    /** @internal */
    public function __construct(
        public int $questId,
        public bool $completed,
    ) {}

    /**
     * @internal
     *
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
