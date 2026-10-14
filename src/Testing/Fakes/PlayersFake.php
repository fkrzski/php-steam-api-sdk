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
use Fkrzski\SteamApiSdk\Testing\SteamResponse;

/**
 * The IPlayerService endpoints, faked the way `$connector->players()` reaches them.
 */
final readonly class PlayersFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function ownedGames(OwnedGameFactory ...$games): SteamFake
    {
        $this->fake->addResponse(SteamResponse::ownedGames(...$games), GetOwnedGamesRequest::class);

        return $this->fake;
    }

    public function ownedGamesNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerServiceNotPublic(), GetOwnedGamesRequest::class);

        return $this->fake;
    }

    public function recentlyPlayedGames(RecentlyPlayedGamesFactory $games): SteamFake
    {
        $this->fake->addResponse(SteamResponse::recentlyPlayedGames($games), GetRecentlyPlayedGamesRequest::class);

        return $this->fake;
    }

    public function recentlyPlayedGamesNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerServiceNotPublic(), GetRecentlyPlayedGamesRequest::class);

        return $this->fake;
    }

    public function steamLevel(int $level): SteamFake
    {
        $this->fake->addResponse(SteamResponse::steamLevel($level), GetSteamLevelRequest::class);

        return $this->fake;
    }

    public function steamLevelNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerServiceNotPublic(), GetSteamLevelRequest::class);

        return $this->fake;
    }

    public function badges(PlayerBadgesFactory $badges): SteamFake
    {
        $this->fake->addResponse(SteamResponse::badges($badges), GetBadgesRequest::class);

        return $this->fake;
    }

    public function badgesNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerServiceNotPublic(), GetBadgesRequest::class);

        return $this->fake;
    }

    public function communityBadgeProgress(CommunityBadgeQuestFactory ...$quests): SteamFake
    {
        $this->fake->addResponse(
            SteamResponse::communityBadgeProgress(...$quests),
            GetCommunityBadgeProgressRequest::class,
        );

        return $this->fake;
    }

    public function communityBadgeProgressNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerServiceNotPublic(), GetCommunityBadgeProgressRequest::class);

        return $this->fake;
    }
}
