<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil;

use DateTimeImmutable;
use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<DateTimeImmutable>
 */
final class GetServerInfoRequest extends SteamApiRequest implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/ISteamWebAPIUtil/GetServerInfo/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['servertime'];
    }

    protected function read(Response $response): DateTimeImmutable
    {
        /** @var array{servertime: int} $body */
        $body = $response->json();

        return new DateTimeImmutable('@'.$body['servertime']);
    }
}
