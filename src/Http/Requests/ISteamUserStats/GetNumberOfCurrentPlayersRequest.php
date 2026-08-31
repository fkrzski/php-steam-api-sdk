<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class GetNumberOfCurrentPlayersRequest extends Request implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly int $appId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUserStats/GetNumberOfCurrentPlayers/v1/';
    }

    /**
     * An app Steam does not know answers 404 with `result: 42` and no count, which the
     * connector would otherwise flatten into a bare SteamApiException.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->status() !== 404) {
            return null;
        }

        return AppNotFoundException::forAppId($this->appId, $response);
    }

    public function createDtoFromResponse(Response $response): int
    {
        /** @var array{response: array{player_count: int}} $body */
        $body = $response->json();

        return $body['response']['player_count'];
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        return [
            'appid' => $this->appId,
        ];
    }
}
