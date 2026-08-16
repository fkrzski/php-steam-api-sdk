<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class GetUserStatsForGameRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
        public readonly int $appId,
        public readonly ?string $language = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUserStats/GetUserStatsForGame/v2/';
    }

    /**
     * A private profile answers 400 with an empty JSON object; a missing API key
     * answers 400 with HTML, which belongs to the connector.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->status() !== 400 || ! json_validate($response->body())) {
            return null;
        }

        return ProfileNotPublicException::forSteamId($this->steamId, $response);
    }

    public function createDtoFromResponse(Response $response): UserStats
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

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [
            'steamid' => $this->steamId->value,
            'appid' => $this->appId,
        ];

        if ($this->language !== null) {
            $query['l'] = $this->language;
        }

        return $query;
    }
}
