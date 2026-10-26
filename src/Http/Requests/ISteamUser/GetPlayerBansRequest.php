<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUser;

use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Exceptions\TooManySteamIdsException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use InvalidArgumentException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<list<PlayerBan>>
 */
final class GetPlayerBansRequest extends SteamApiRequest
{
    public const int MAX_STEAM_IDS = 100;

    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<SteamId>  $steamIds
     */
    public function __construct(
        public readonly array $steamIds,
    ) {
        $count = count($steamIds);

        if ($count === 0) {
            throw new InvalidArgumentException('GetPlayerBansRequest requires at least one SteamId.');
        }

        if ($count > self::MAX_STEAM_IDS) {
            throw TooManySteamIdsException::forCount($count, 'GetPlayerBans');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/ISteamUser/GetPlayerBans/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['players'];
    }

    /**
     * @return list<PlayerBan>
     */
    protected function read(Response $response): array
    {
        /**
         * Unlike the other ISteamUser endpoints, GetPlayerBans returns the player
         * list at the top level rather than nested under a "response" key.
         *
         * @var array{players?: list<array{
         *     SteamId: string,
         *     CommunityBanned: bool,
         *     VACBanned: bool,
         *     NumberOfVACBans: int,
         *     DaysSinceLastBan: int,
         *     NumberOfGameBans: int,
         *     EconomyBan: string,
         * }>} $body
         */
        $body = $response->json();
        $players = $body['players'] ?? [];

        return array_map(PlayerBan::fromArray(...), $players);
    }

    protected function defaultQuery(): array
    {
        return [
            'steamids' => implode(',', $this->steamIds),
        ];
    }
}
