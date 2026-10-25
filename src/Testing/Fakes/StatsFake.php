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
use Saloon\Http\Faking\MockResponse;

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
        $this->fake->addResponse(MockResponse::make([
            'playerstats' => $stats->toArray(),
        ]), GetUserStatsForGameRequest::class);

        return $this->fake;
    }

    /**
     * A hidden profile `GetUserStatsForGame` answers with 400 and an empty JSON object,
     * raised as `ProfileNotPublicException`.
     */
    public function userStatsNotPublic(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make('{}', 400), GetUserStatsForGameRequest::class);

        return $this->fake;
    }

    public function achievements(PlayerAchievementsFactory $achievements): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'playerstats' => $achievements->toArray(),
        ]), GetPlayerAchievementsRequest::class);

        return $this->fake;
    }

    /**
     * `GetPlayerAchievements` answers 400 with this body for an app without stats and for
     * a hidden profile alike, raising `StatsUnavailableException` either way.
     */
    public function achievementsUnavailable(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'playerstats' => [
                'error' => 'Requested app has no stats',
                'success' => false,
            ],
        ], 400), GetPlayerAchievementsRequest::class);

        return $this->fake;
    }

    public function currentPlayers(int $count): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'player_count' => $count,
                'result' => 1,
            ],
        ]), GetNumberOfCurrentPlayersRequest::class);

        return $this->fake;
    }

    /**
     * An app ID Steam does not know, which `GetNumberOfCurrentPlayers` raises as
     * `AppNotFoundException`. The request reads the status alone; `result: 42` is
     * here because that is what Steam sends with it.
     */
    public function currentPlayersAppNotFound(): SteamFake
    {
        $this->fake->addResponse(
            MockResponse::make(['response' => ['result' => 42]], 404),
            GetNumberOfCurrentPlayersRequest::class,
        );

        return $this->fake;
    }

    public function globalAchievements(GlobalAchievementFactory ...$achievements): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'achievementpercentages' => [
                'achievements' => array_map(
                    static fn (GlobalAchievementFactory $achievement): array => $achievement->toArray(),
                    $achievements,
                ),
            ],
        ]), GetGlobalAchievementPercentagesForAppRequest::class);

        return $this->fake;
    }

    /**
     * `GetGlobalAchievementPercentagesForApp` answers 403 with an empty JSON object
     * for a game carrying no achievements and for a game ID it does not know alike,
     * raising `StatsUnavailableException` either way. The request claims this status
     * before the connector can read it as a rejected key or a hidden profile.
     */
    public function globalAchievementsUnavailable(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make('{}', 403), GetGlobalAchievementPercentagesForAppRequest::class);

        return $this->fake;
    }

    public function schema(GameSchemaFactory $schema): SteamFake
    {
        $game = $schema->toArray();

        // An empty array encodes as `[]`, where Steam sends `{}`.
        $this->fake->addResponse(MockResponse::make([
            'game' => $game === [] ? (object) [] : $game,
        ]), GetSchemaForGameRequest::class);

        return $this->fake;
    }

    /**
     * An app ID `GetSchemaForGame` does not know, which it raises as `AppNotFoundException`.
     */
    public function schemaAppNotFound(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make('{}', 400), GetSchemaForGameRequest::class);

        return $this->fake;
    }
}
