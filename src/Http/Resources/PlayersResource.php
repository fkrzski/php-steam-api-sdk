<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Resources;

use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;
use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\BaseResource;

final class PlayersResource extends BaseResource
{
    /**
     * @param  list<int>  $appIdsFilter
     * @return list<OwnedGame>
     */
    public function ownedGames(
        SteamId $steamId,
        array $appIdsFilter = [],
        bool $includeAppInfo = false,
        bool $includePlayedFreeGames = false,
    ): array {
        $request = new GetOwnedGamesRequest(
            $steamId,
            $appIdsFilter,
            $includeAppInfo,
            $includePlayedFreeGames,
        );

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function recentlyPlayedGames(SteamId $steamId, ?int $count = null): RecentlyPlayedGames
    {
        $request = new GetRecentlyPlayedGamesRequest($steamId, $count);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function steamLevel(SteamId $steamId): int
    {
        $request = new GetSteamLevelRequest($steamId);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function badges(SteamId $steamId): PlayerBadges
    {
        $request = new GetBadgesRequest($steamId);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    /**
     * @return list<CommunityBadgeQuest>
     */
    public function communityBadgeProgress(SteamId $steamId): array
    {
        $request = new GetCommunityBadgeProgressRequest($steamId);

        return $request->createDtoFromResponse($this->connector->send($request));
    }
}
