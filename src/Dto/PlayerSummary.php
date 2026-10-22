<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use DateTimeImmutable;
use Fkrzski\SteamApiSdk\Enums\CommentPermission;
use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;
use Fkrzski\SteamApiSdk\Enums\PersonaState;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

final readonly class PlayerSummary
{
    /** The clan base with account ID 0, which Steam sends for a player without a primary group. */
    private const string NO_PRIMARY_CLAN_ID = '103582791429521408';

    /** @internal */
    public function __construct(
        public SteamId $steamId,
        public string $personaName,
        public string $profileUrl,
        public string $avatarUrl,
        public string $avatarMediumUrl,
        public string $avatarFullUrl,
        public string $avatarHash,
        public CommunityVisibility $communityVisibility,
        public bool $hasCommunityProfile,
        public CommentPermission $commentPermission,
        public PersonaState $personaState,
        public ?string $realName,
        public ?string $primaryClanId,
        public ?DateTimeImmutable $timeCreated,
        public ?DateTimeImmutable $lastLogOff,
        public ?string $gameId,
        public ?string $gameExtraInfo,
        public ?string $gameServerIp,
        public ?string $countryCode,
        public ?string $stateCode,
        public ?int $cityId,
    ) {}

    /**
     * @internal
     *
     * @param  array{
     *     steamid: string,
     *     personaname: string,
     *     profileurl: string,
     *     avatar: string,
     *     avatarmedium: string,
     *     avatarfull: string,
     *     avatarhash: string,
     *     communityvisibilitystate: int,
     *     profilestate?: int,
     *     commentpermission?: int,
     *     personastate?: int,
     *     realname?: string,
     *     primaryclanid?: string,
     *     timecreated?: int,
     *     lastlogoff?: int,
     *     gameid?: string,
     *     gameextrainfo?: string,
     *     gameserverip?: string,
     *     loccountrycode?: string,
     *     locstatecode?: string,
     *     loccityid?: int,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        $primaryClanId = $payload['primaryclanid'] ?? null;

        return new self(
            steamId: SteamId::fromSteamId64($payload['steamid']),
            personaName: $payload['personaname'],
            profileUrl: $payload['profileurl'],
            avatarUrl: $payload['avatar'],
            avatarMediumUrl: $payload['avatarmedium'],
            avatarFullUrl: $payload['avatarfull'],
            avatarHash: $payload['avatarhash'],
            communityVisibility: CommunityVisibility::fromApiValue($payload['communityvisibilitystate']),
            hasCommunityProfile: isset($payload['profilestate']) && $payload['profilestate'] === 1,
            commentPermission: CommentPermission::fromApiValue($payload['commentpermission'] ?? null),
            personaState: PersonaState::fromApiValue($payload['personastate'] ?? 0),
            realName: $payload['realname'] ?? null,
            primaryClanId: $primaryClanId !== self::NO_PRIMARY_CLAN_ID ? $primaryClanId : null,
            timeCreated: isset($payload['timecreated']) ? self::timestamp($payload['timecreated']) : null,
            lastLogOff: isset($payload['lastlogoff']) ? self::timestamp($payload['lastlogoff']) : null,
            gameId: $payload['gameid'] ?? null,
            gameExtraInfo: $payload['gameextrainfo'] ?? null,
            gameServerIp: $payload['gameserverip'] ?? null,
            countryCode: $payload['loccountrycode'] ?? null,
            stateCode: $payload['locstatecode'] ?? null,
            cityId: $payload['loccityid'] ?? null,
        );
    }

    private static function timestamp(int $unix): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$unix);
    }
}
