<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\GlobalAchievement;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class GetGlobalAchievementPercentagesForAppRequest extends Request implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * Valve spells this endpoint's identifier `gameid`; the value is an ordinary app ID.
     */
    public function __construct(
        public readonly int $gameId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUserStats/GetGlobalAchievementPercentagesForApp/v2/';
    }

    /**
     * Steam answers 403 with an empty JSON object for a game carrying no achievements
     * and for a game ID it does not know alike, so the cause cannot be recovered. No key
     * goes out on this endpoint, which is what leaves every 403 to this request.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->status() !== 403) {
            return null;
        }

        return StatsUnavailableException::forGlobalAchievements($this->gameId, $response);
    }

    /**
     * @return list<GlobalAchievement>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /**
         * @var array{achievementpercentages: array{achievements: list<array{
         *     name: string,
         *     percent: string,
         * }>}} $body
         */
        $body = $response->json();

        return array_map(GlobalAchievement::fromArray(...), $body['achievementpercentages']['achievements']);
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        return [
            'gameid' => $this->gameId,
        ];
    }
}
