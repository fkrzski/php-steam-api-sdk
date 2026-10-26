<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\GlobalAchievement;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<list<GlobalAchievement>>
 */
final class GetGlobalAchievementPercentagesForAppRequest extends SteamApiRequest implements SendsNoApiKey
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

    public function requiredPaths(Response $response): array
    {
        return ['achievementpercentages.achievements'];
    }

    /**
     * Steam answers 403 with an empty JSON object for a game carrying no achievements
     * and for a game ID it does not know alike, so the cause cannot be recovered. No key
     * goes out on this endpoint, which is what leaves every 403 to this request.
     */
    #[Override]
    protected function readError(Response $response): ?StatsUnavailableException
    {
        if ($response->status() !== 403) {
            return null;
        }

        return StatsUnavailableException::forGlobalAchievements($this->gameId, $response);
    }

    /**
     * @return list<GlobalAchievement>
     */
    protected function read(Response $response): array
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

    protected function defaultQuery(): array
    {
        return [
            'gameid' => $this->gameId,
        ];
    }
}
