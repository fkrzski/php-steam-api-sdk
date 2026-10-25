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
            static fn (PendingRequest $pendingRequest): MockResponse => MockResponse::make([
                'response' => [
                    'players' => array_map(
                        static fn (PlayerSummaryFactory $player): array => $player->toArray(),
                        $askedFor($pendingRequest),
                    ),
                ],
            ]),
            GetPlayerSummariesRequest::class,
        );

        return $this->fake;
    }

    /**
     * Each request gets the bans it asks for, or, asking for none, the ones no request has had yet.
     * Ban records sit at the top level, without the `response` wrapper every other ISteamUser endpoint uses.
     */
    public function bans(PlayerBanFactory ...$bans): SteamFake
    {
        $askedFor = $this->askedFor('SteamId', $bans);

        $this->fake->addResponse(
            static fn (PendingRequest $pendingRequest): MockResponse => MockResponse::make([
                'players' => array_map(
                    static fn (PlayerBanFactory $ban): array => $ban->toArray(),
                    $askedFor($pendingRequest),
                ),
            ]),
            GetPlayerBansRequest::class,
        );

        return $this->fake;
    }

    public function friends(FriendFactory ...$friends): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'friendslist' => [
                'friends' => array_map(
                    static fn (FriendFactory $friend): array => $friend->toArray(),
                    $friends,
                ),
            ],
        ]), GetFriendListRequest::class);

        return $this->fake;
    }

    /**
     * A hidden friend list, which the connector raises from the bare 401 as `ProfileNotPublicException`.
     */
    public function friendsNotPublic(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make('{}', 401), GetFriendListRequest::class);

        return $this->fake;
    }

    public function groups(UserGroupFactory ...$groups): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'success' => true,
                'groups' => array_map(
                    static fn (UserGroupFactory $group): array => $group->toArray(),
                    $groups,
                ),
            ],
        ]), GetUserGroupListRequest::class);

        return $this->fake;
    }

    /**
     * A hidden group list, which the connector raises from the 403 as `ProfileNotPublicException`.
     */
    public function groupsNotPublic(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'success' => false,
                'error' => 'Private profile',
            ],
        ], 403), GetUserGroupListRequest::class);

        return $this->fake;
    }

    public function resolveVanityUrl(SteamId $steamId): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'steamid' => $steamId->value,
                'success' => 1,
            ],
        ]), ResolveVanityUrlRequest::class);

        return $this->fake;
    }

    /**
     * `ResolveVanityURL` reports an unclaimed slug in the body, not the status.
     */
    public function vanityUrlNotFound(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'success' => 42,
                'message' => 'No match',
            ],
        ]), ResolveVanityUrlRequest::class);

        return $this->fake;
    }

    /**
     * Summaries and bans past 100 IDs go out in chunks, so a player one chunk got unasked must not come back in another.
     *
     * @template TFactory of PlayerSummaryFactory|PlayerBanFactory
     *
     * @param  array<TFactory>  $players
     * @return Closure(PendingRequest): list<TFactory>
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

            // The keys track who got whom; Steam's list carries none.
            return array_values($asked);
        };
    }
}
