<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Resources;

use DateTimeImmutable;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Saloon\Http\BaseResource;

final class WebApiResource extends BaseResource
{
    public function serverTime(): DateTimeImmutable
    {
        $request = new GetServerInfoRequest;

        return $request->createDtoFromResponse($this->connector->send($request));
    }
}
