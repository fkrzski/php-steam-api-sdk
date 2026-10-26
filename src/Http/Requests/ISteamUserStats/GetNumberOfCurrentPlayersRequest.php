<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<int>
 */
final class GetNumberOfCurrentPlayersRequest extends SteamApiRequest implements SendsNoApiKey
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

    public function requiredPaths(Response $response): array
    {
        return ['response.player_count'];
    }

    /**
     * An app Steam does not know answers 404 with `result: 42` and no count, which the
     * connector would otherwise flatten into a bare SteamApiException.
     */
    #[Override]
    protected function readError(Response $response): ?AppNotFoundException
    {
        if ($response->status() !== 404) {
            return null;
        }

        return AppNotFoundException::forAppId($this->appId, $response);
    }

    protected function read(Response $response): int
    {
        /** @var array{response: array{player_count: int}} $body */
        $body = $response->json();

        return $body['response']['player_count'];
    }

    protected function defaultQuery(): array
    {
        return [
            'appid' => $this->appId,
        ];
    }
}
