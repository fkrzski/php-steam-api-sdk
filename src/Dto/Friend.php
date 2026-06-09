<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use DateTimeImmutable;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

final readonly class Friend
{
    public function __construct(
        public SteamId $steamId,
        public FriendRelationship $relationship,
        public DateTimeImmutable $friendSince,
    ) {}

    /**
     * @param  array{
     *     steamid: string,
     *     relationship: string,
     *     friend_since: int,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            steamId: SteamId::fromSteamId64($payload['steamid']),
            relationship: FriendRelationship::from($payload['relationship']),
            friendSince: (new DateTimeImmutable)->setTimestamp($payload['friend_since']),
        );
    }
}
