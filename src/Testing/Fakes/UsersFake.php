<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use Closure;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\FriendFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerBanFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\UserGroupFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * The ISteamUser endpoints, faked the way `$connector->users()` reaches them.
 */
final readonly class UsersFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    /**
     * Each request gets the players it asks for, or, asking for none, the ones no request has had yet.
     */
    public function summaries(PlayerSummaryFactory ...$players): SteamFake
    {
        $askedFor = $this->askedFor('steamid', $players);

        $this->fake->addResponse(
            static fn (PendingRequest $pendingRequest): MockResponse => SteamResponse::playerSummaries(...$askedFor($pendingRequest)),
            GetPlayerSummariesRequest::class,
        );

        return $this->fake;
    }

    /**
     * Each request gets the bans it asks for, or, asking for none, the ones no request has had yet.
     */
    public function bans(PlayerBanFactory ...$bans): SteamFake
    {
        $askedFor = $this->askedFor('SteamId', $bans);

        $this->fake->addResponse(
            static fn (PendingRequest $pendingRequest): MockResponse => SteamResponse::playerBans(...$askedFor($pendingRequest)),
            GetPlayerBansRequest::class,
        );

        return $this->fake;
    }

    public function friends(FriendFactory ...$friends): SteamFake
    {
        $this->fake->addResponse(SteamResponse::friendList(...$friends), GetFriendListRequest::class);

        return $this->fake;
    }

    public function friendsNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::profileNotPublic(), GetFriendListRequest::class);

        return $this->fake;
    }

    public function groups(UserGroupFactory ...$groups): SteamFake
    {
        $this->fake->addResponse(SteamResponse::userGroupList(...$groups), GetUserGroupListRequest::class);

        return $this->fake;
    }

    public function groupsNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::profileNotPublic(), GetUserGroupListRequest::class);

        return $this->fake;
    }

    public function resolveVanityUrl(SteamId $steamId): SteamFake
    {
        $this->fake->addResponse(SteamResponse::vanityUrl($steamId), ResolveVanityUrlRequest::class);

        return $this->fake;
    }

    public function vanityUrlNotFound(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::vanityNotFound(), ResolveVanityUrlRequest::class);

        return $this->fake;
    }

    /**
     * Summaries and bans past 100 IDs go out in chunks, so a player one chunk got unasked must not come back in another.
     *
     * @template TFactory of PlayerSummaryFactory|PlayerBanFactory
     *
     * @param  array<TFactory>  $players
     * @return Closure(PendingRequest): array<TFactory>
     */
    private function askedFor(string $key, array $players): Closure
    {
        $unclaimed = $players;
        $handedOut = [];

        return static function (PendingRequest $pendingRequest) use ($key, $players, &$unclaimed, &$handedOut): array {
            /** @var GetPlayerSummariesRequest|GetPlayerBansRequest $request */
            $request = $pendingRequest->getRequest();

            $asked = array_filter(
                $players,
                static fn (PlayerSummaryFactory|PlayerBanFactory $player): bool => array_any(
                    $request->steamIds,
                    static fn (SteamId $steamId): bool => $steamId->value === $player->toArray()[$key],
                ),
            );

            if ($asked === []) {
                $asked = $unclaimed;
                $handedOut += $unclaimed;
            } else {
                $asked = array_diff_key($asked, $handedOut);
            }

            $unclaimed = array_diff_key($unclaimed, $asked);

            return $asked;
        };
    }
}
