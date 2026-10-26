<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<RecentlyPlayedGames>
 */
final class GetRecentlyPlayedGamesRequest extends SteamApiRequest
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
        public readonly ?int $count = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/IPlayerService/GetRecentlyPlayedGames/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['response'];
    }

    /**
     * A player with nothing played still gets `total_count: 0`; only a withheld profile
     * drops the key, which makes its absence the discriminator.
     */
    protected function read(Response $response): RecentlyPlayedGames|ProfileNotPublicException
    {
        /**
         * @var array{response: array{total_count?: int, games?: list<array{
         *     appid: int,
         *     name: string,
         *     playtime_2weeks: int,
         *     playtime_forever: int,
         *     img_icon_url: string,
         *     playtime_windows_forever: int,
         *     playtime_mac_forever: int,
         *     playtime_linux_forever: int,
         *     playtime_deck_forever: int,
         * }>}} $body
         */
        $body = $response->json();

        if (! array_key_exists('total_count', $body['response'])) {
            return ProfileNotPublicException::forPrivateOrMissing($this->steamId, $response);
        }

        return RecentlyPlayedGames::fromArray($body['response']);
    }

    protected function defaultQuery(): array
    {
        $query = ['steamid' => $this->steamId->value];

        if ($this->count !== null) {
            $query['count'] = $this->count;
        }

        return $query;
    }
}
