<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

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
     * A private profile answers 200 too, only without `game_count`.
     */
    #[Override]
    public function hasRequestFailed(Response $response): ?bool
    {
        // No opinion off a 200: false would overrule Saloon's own 4xx and 5xx check.
        if ($response->status() !== 200) {
            return null;
        }

        /** @var array{response?: array{game_count?: int}} $body */
        $body = $response->json();

        return ! array_key_exists('game_count', $body['response'] ?? []);
    }

    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->hasRequestFailed($response) === true
            ? ProfileNotPublicException::forSteamId($this->steamId, $response)
            : null;
    }

    /**
     * @return list<OwnedGame>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /**
         * @var array{response: array{game_count: int, games?: list<array{
         *     appid: int,
         *     playtime_forever: int,
         *     playtime_2weeks?: int,
         *     name?: string,
         *     img_icon_url?: string,
         *     has_community_visible_stats?: bool,
         * }>}} $body
         */
        $body = $response->json();

        return array_map(OwnedGame::fromArray(...), $body['response']['games'] ?? []);
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
