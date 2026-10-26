<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<UserStats>
 */
final class GetUserStatsForGameRequest extends SteamApiRequest
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
        public readonly int $appId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUserStats/GetUserStatsForGame/v2/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['playerstats'];
    }

    /**
     * A private profile answers 400 with an empty JSON object; a missing API key
     * answers 400 with HTML, which belongs to the connector.
     */
    #[Override]
    protected function readError(Response $response): ?ProfileNotPublicException
    {
        if ($response->status() !== 400 || ! json_validate($response->body())) {
            return null;
        }

        return ProfileNotPublicException::forSteamId($this->steamId, $response);
    }

    protected function read(Response $response): UserStats
    {
        /**
         * @var array{playerstats: array{
         *     steamID: string,
         *     gameName: string,
         *     stats?: list<array{name: string, value: int|float}>,
         *     achievements?: list<array{name: string, achieved: int}>,
         * }} $body
         */
        $body = $response->json();

        return UserStats::fromArray($body['playerstats']);
    }

    protected function defaultQuery(): array
    {
        return [
            'steamid' => $this->steamId->value,
            'appid' => $this->appId,
        ];
    }
}
