<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Resources;

use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Dto\GameServer;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Saloon\Http\BaseResource;

final class AppsResource extends BaseResource
{
    /**
     * @return list<GameServer>
     */
    public function serversAtAddress(string $address): array
    {
        $request = new GetServersAtAddressRequest($address);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function upToDateCheck(int $appId, int $version): AppVersionCheck
    {
        $request = new UpToDateCheckRequest($appId, $version);

        return $request->createDtoFromResponse($this->connector->send($request));
    }
}
