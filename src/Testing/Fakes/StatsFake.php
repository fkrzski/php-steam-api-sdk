<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GameSchemaFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GlobalAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatsFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;

/**
 * The ISteamUserStats endpoints, faked the way `$connector->stats()` reaches them.
 */
final readonly class StatsFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function userStats(UserStatsFactory $stats): SteamFake
    {
        $this->fake->addResponse(SteamResponse::userStats($stats), GetUserStatsForGameRequest::class);

        return $this->fake;
    }

    public function userStatsNotPublic(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::statsRefused(), GetUserStatsForGameRequest::class);

        return $this->fake;
    }

    public function achievements(PlayerAchievementsFactory $achievements): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerAchievements($achievements), GetPlayerAchievementsRequest::class);

        return $this->fake;
    }

    public function achievementsUnavailable(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::playerAchievementsRefused(), GetPlayerAchievementsRequest::class);

        return $this->fake;
    }

    public function currentPlayers(int $count): SteamFake
    {
        $this->fake->addResponse(SteamResponse::currentPlayers($count), GetNumberOfCurrentPlayersRequest::class);

        return $this->fake;
    }

    public function currentPlayersAppNotFound(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::appNotFound(), GetNumberOfCurrentPlayersRequest::class);

        return $this->fake;
    }

    public function globalAchievements(GlobalAchievementFactory ...$achievements): SteamFake
    {
        $this->fake->addResponse(
            SteamResponse::globalAchievements(...$achievements),
            GetGlobalAchievementPercentagesForAppRequest::class,
        );

        return $this->fake;
    }

    public function globalAchievementsUnavailable(): SteamFake
    {
        $this->fake->addResponse(
            SteamResponse::globalAchievementsRefused(),
            GetGlobalAchievementPercentagesForAppRequest::class,
        );

        return $this->fake;
    }

    public function schema(GameSchemaFactory $schema): SteamFake
    {
        $this->fake->addResponse(SteamResponse::gameSchema($schema), GetSchemaForGameRequest::class);

        return $this->fake;
    }

    public function schemaAppNotFound(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::schemaAppNotFound(), GetSchemaForGameRequest::class);

        return $this->fake;
    }
}
