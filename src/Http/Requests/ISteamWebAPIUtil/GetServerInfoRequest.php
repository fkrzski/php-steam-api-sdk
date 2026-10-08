<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil;

use DateTimeImmutable;
use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetServerInfoRequest extends Request implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/ISteamWebAPIUtil/GetServerInfo/v1/';
    }

    public function createDtoFromResponse(Response $response): DateTimeImmutable
    {
        /** @var array{servertime: int} $body */
        $body = $response->json();

        return new DateTimeImmutable('@'.$body['servertime']);
    }
}
