<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Resources;

use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\BaseResource;

final class UsersResource extends BaseResource
{
    /**
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerSummary>
     */
    public function summaries(array $steamIds): array
    {
        $request = new GetPlayerSummariesRequest($steamIds);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    /**
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerBan>
     */
    public function bans(array $steamIds): array
    {
        $request = new GetPlayerBansRequest($steamIds);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    /**
     * @return list<Friend>
     */
    public function friends(SteamId $steamId, ?FriendRelationship $relationship = null): array
    {
        $request = new GetFriendListRequest($steamId, $relationship);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    /**
     * @return list<UserGroup>
     */
    public function groups(SteamId $steamId): array
    {
        $request = new GetUserGroupListRequest($steamId);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function resolveVanityUrl(string $vanityName): SteamId
    {
        $request = new ResolveVanityUrlRequest($vanityName);

        return $request->createDtoFromResponse($this->connector->send($request));
    }
}
