<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\CommunityBadgeQuestFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\OwnedGameFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\PlayerBadgesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGamesFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Faking\MockResponse;

/**
 * The IPlayerService endpoints, faked the way `$connector->players()` reaches them.
 */
final readonly class PlayersFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    /**
     * `game_count` is what tells the SDK the profile is public — see
     * {@see self::ownedGamesNotPublic()} for the response that omits it.
     */
    public function ownedGames(OwnedGameFactory ...$games): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'game_count' => count($games),
                'games' => array_map(
                    static fn (OwnedGameFactory $game): array => $game->toArray(),
                    $games,
                ),
            ],
        ]), GetOwnedGamesRequest::class);

        return $this->fake;
    }

    public function ownedGamesNotPublic(): SteamFake
    {
        return $this->notPublic(GetOwnedGamesRequest::class);
    }

    /**
     * `total_count` is Steam's own total for the window, so it is independent of how
     * many games the payload lists — see {@see RecentlyPlayedGamesFactory::totalCount()}.
     */
    public function recentlyPlayedGames(RecentlyPlayedGamesFactory $games): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => $games->toArray(),
        ]), GetRecentlyPlayedGamesRequest::class);

        return $this->fake;
    }

    public function recentlyPlayedGamesNotPublic(): SteamFake
    {
        return $this->notPublic(GetRecentlyPlayedGamesRequest::class);
    }

    public function steamLevel(int $level): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => ['player_level' => $level],
        ]), GetSteamLevelRequest::class);

        return $this->fake;
    }

    public function steamLevelNotPublic(): SteamFake
    {
        return $this->notPublic(GetSteamLevelRequest::class);
    }

    /**
     * `player_level` is what tells the SDK the profile is public — a hidden one
     * answers with the empty `response` object {@see self::badgesNotPublic()} fakes.
     */
    public function badges(PlayerBadgesFactory $badges): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => $badges->toArray(),
        ]), GetBadgesRequest::class);

        return $this->fake;
    }

    public function badgesNotPublic(): SteamFake
    {
        return $this->notPublic(GetBadgesRequest::class);
    }

    public function communityBadgeProgress(CommunityBadgeQuestFactory ...$quests): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'quests' => array_map(
                    static fn (CommunityBadgeQuestFactory $quest): array => $quest->toArray(),
                    $quests,
                ),
            ],
        ]), GetCommunityBadgeProgressRequest::class);

        return $this->fake;
    }

    public function communityBadgeProgressNotPublic(): SteamFake
    {
        return $this->notPublic(GetCommunityBadgeProgressRequest::class);
    }

    /**
     * Every IPlayerService endpoint answers a hidden profile with 200 and an empty
     * `response` object, dropping only the key it reads — `game_count`, `total_count`,
     * `player_level`. Nothing but that absence separates it from a player who owns or
     * played nothing, so this cannot be expressed as a status code.
     */
    private function notPublic(string $request): SteamFake
    {
        $this->fake->addResponse(MockResponse::make(['response' => (object) []]), $request);

        return $this->fake;
    }
}
