<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class GetPlayerAchievementsRequest extends Request
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
        return '/ISteamUserStats/GetPlayerAchievements/v1/';
    }

    /**
     * Steam answers 400 identically for an app without stats and for a private
     * profile, so the cause cannot be recovered. A missing API key also lands on
     * 400 but as HTML, which the connector claims.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->status() !== 400 || ! json_validate($response->body())) {
            return null;
        }

        return StatsUnavailableException::forAppId($this->appId, $response);
    }

    public function createDtoFromResponse(Response $response): PlayerAchievements
    {
        /**
         * @var array{playerstats: array{
         *     steamID: string,
         *     gameName: string,
         *     achievements: list<array{apiname: string, achieved: int, unlocktime: int, name?: string, description?: string}>,
         *     success: bool,
         * }} $body
         */
        $body = $response->json();

        return PlayerAchievements::fromArray($body['playerstats']);
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
