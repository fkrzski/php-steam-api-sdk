<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests;

use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetOwnedGamesRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<int>  $appIdsFilter
     */
    public function __construct(
        public readonly SteamId $steamId,
        public readonly array $appIdsFilter = [],
        public readonly bool $includeAppInfo = false,
        public readonly bool $includePlayedFreeGames = false,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/IPlayerService/GetOwnedGames/v1/';
    }

    /**
     * @return list<OwnedGame>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /**
         * @var array{response?: array{game_count?: int, games?: list<array{
         *     appid: int,
         *     playtime_forever: int,
         *     playtime_2weeks?: int,
         *     name?: string,
         *     img_icon_url?: string,
         *     has_community_visible_stats?: bool,
         * }>}} $body
         */
        $body = $response->json();
        $responseBody = $body['response'] ?? [];

        if (! array_key_exists('game_count', $responseBody)) {
            throw new ProfileNotPublicException('Steam profile is not public.');
        }

        return array_map(OwnedGame::fromArray(...), $responseBody['games'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = ['steamid' => $this->steamId->value];

        if ($this->appIdsFilter !== []) {
            $query['appids_filter'] = $this->appIdsFilter;
        }

        if ($this->includeAppInfo) {
            $query['include_appinfo'] = 1;
        }

        if ($this->includePlayedFreeGames) {
            $query['include_played_free_games'] = 1;
        }

        return $query;
    }
}
