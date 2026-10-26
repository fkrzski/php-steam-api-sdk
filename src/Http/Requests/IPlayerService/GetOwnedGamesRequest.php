<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<list<OwnedGame>>
 */
final class GetOwnedGamesRequest extends SteamApiRequest
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

    public function requiredPaths(Response $response): array
    {
        return ['response'];
    }

    /**
     * A private profile answers 200 too, only without `game_count`.
     *
     * @return list<OwnedGame>|ProfileNotPublicException
     */
    protected function read(Response $response): array|ProfileNotPublicException
    {
        /**
         * @var array{response: array{game_count?: int, games?: list<array{
         *     appid: int,
         *     playtime_forever: int,
         *     playtime_2weeks?: int,
         *     name?: string,
         *     img_icon_url?: string,
         *     has_community_visible_stats?: bool,
         * }>}} $body
         */
        $body = $response->json();

        if (! array_key_exists('game_count', $body['response'])) {
            return ProfileNotPublicException::forSteamId($this->steamId, $response);
        }

        return array_map(OwnedGame::fromArray(...), $body['response']['games'] ?? []);
    }

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
