<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use DateTimeImmutable;
use Fkrzski\SteamApiSdk\Enums\CommentPermission;
use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;
use Fkrzski\SteamApiSdk\Enums\PersonaState;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use InvalidArgumentException;

final readonly class PlayerSummary
{
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
        public DateTimeImmutable $timeCreated,
        public ?DateTimeImmutable $lastLogOff,
        public ?string $gameId,
        public ?string $gameExtraInfo,
        public ?string $gameServerIp,
        public ?string $countryCode,
        public ?string $stateCode,
        public ?int $cityId,
    ) {}

    /**
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
     *     timecreated: int,
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
        return new self(
            steamId: SteamId::fromSteamId64($payload['steamid']),
            personaName: $payload['personaname'],
            profileUrl: $payload['profileurl'],
            avatarUrl: $payload['avatar'],
            avatarMediumUrl: $payload['avatarmedium'],
            avatarFullUrl: $payload['avatarfull'],
            avatarHash: $payload['avatarhash'],
            communityVisibility: CommunityVisibility::fromApiValue($payload['communityvisibilitystate']),
            hasCommunityProfile: ($payload['profilestate'] ?? 0) === 1,
            commentPermission: CommentPermission::fromApiValue($payload['commentpermission'] ?? null),
            personaState: PersonaState::from($payload['personastate'] ?? 0),
            realName: $payload['realname'] ?? null,
            primaryClanId: $payload['primaryclanid'] ?? null,
            timeCreated: self::timestamp($payload['timecreated']),
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
        $dt = DateTimeImmutable::createFromFormat('U', (string) $unix);

        if ($dt === false) {
            throw new InvalidArgumentException(sprintf('Invalid unix timestamp "%d".', $unix));
        }

        return $dt;
    }
}
