<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests;

use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Exceptions\TooManySteamIdsException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use InvalidArgumentException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetPlayerSummariesRequest extends Request
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
            throw new InvalidArgumentException('GetPlayerSummariesRequest requires at least one SteamId.');
        }

        if ($count > self::MAX_STEAM_IDS) {
            throw TooManySteamIdsException::forCount($count);
        }
    }

    public function resolveEndpoint(): string
    {
        return '/ISteamUser/GetPlayerSummaries/v2/';
    }

    /**
     * @return list<PlayerSummary>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /**
         * @var array{response?: array{players?: list<array{
         *     steamid: string,
         *     personaname: string,
         *     profileurl: string,
         *     avatar: string,
         *     avatarmedium: string,
         *     avatarfull: string,
         *     avatarhash: string,
         *     communityvisibilitystate: int,
         *     profilestate?: int,
         *     commentpermission?: int,
         *     personastate?: int,
         *     realname?: string,
         *     primaryclanid?: string,
         *     timecreated: int,
         *     lastlogoff?: int,
         *     gameid?: string,
         *     gameextrainfo?: string,
         *     gameserverip?: string,
         *     loccountrycode?: string,
         *     locstatecode?: string,
         *     loccityid?: int,
         * }>}} $body
         */
        $body = $response->json();
        $players = $body['response']['players'] ?? [];

        return array_map(PlayerSummary::fromArray(...), $players);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'steamids' => implode(',', $this->steamIds),
        ];
    }
}
