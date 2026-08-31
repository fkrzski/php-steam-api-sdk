<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Resources;

use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\GlobalAchievement;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\BaseResource;

final class StatsResource extends BaseResource
{
    public function achievements(SteamId $steamId, int $appId, ?Language $language = null): PlayerAchievements
    {
        $request = new GetPlayerAchievementsRequest($steamId, $appId, $language);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function currentPlayers(int $appId): int
    {
        $request = new GetNumberOfCurrentPlayersRequest($appId);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    /**
     * @return list<GlobalAchievement>
     */
    public function globalAchievements(int $gameId): array
    {
        $request = new GetGlobalAchievementPercentagesForAppRequest($gameId);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function schema(int $appId, ?Language $language = null): GameSchema
    {
        $request = new GetSchemaForGameRequest($appId, $language);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function userStats(SteamId $steamId, int $appId, ?Language $language = null): UserStats
    {
        $request = new GetUserStatsForGameRequest($steamId, $appId, $language);

        return $request->createDtoFromResponse($this->connector->send($request));
    }
}
