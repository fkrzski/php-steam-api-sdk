<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use Fkrzski\SteamApiSdk\Enums\EconomyBan;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

final readonly class PlayerBan
{
    /** @internal */
    public function __construct(
        public SteamId $steamId,
        public bool $isCommunityBanned,
        public bool $isVacBanned,
        public int $numberOfVacBans,
        public int $numberOfGameBans,
        /** Also 0 for a player never banned; read it with the ban counts. */
        public int $daysSinceLastBan,
        public EconomyBan $economyBan,
    ) {}

    /**
     * @internal
     *
     * @param  array{
     *     SteamId: string,
     *     CommunityBanned: bool,
     *     VACBanned: bool,
     *     NumberOfVACBans: int,
     *     DaysSinceLastBan: int,
     *     NumberOfGameBans: int,
     *     EconomyBan: string,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            steamId: SteamId::fromSteamId64($payload['SteamId']),
            isCommunityBanned: $payload['CommunityBanned'],
            isVacBanned: $payload['VACBanned'],
            numberOfVacBans: $payload['NumberOfVACBans'],
            numberOfGameBans: $payload['NumberOfGameBans'],
            daysSinceLastBan: $payload['DaysSinceLastBan'],
            economyBan: EconomyBan::fromApiValue($payload['EconomyBan']),
        );
    }
}
