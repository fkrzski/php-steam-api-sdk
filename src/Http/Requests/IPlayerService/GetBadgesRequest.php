<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<PlayerBadges>
 */
final class GetBadgesRequest extends SteamApiRequest
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/IPlayerService/GetBadges/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['response'];
    }

    /**
     * A SteamID64 that belongs to no account still gets the zeroed progress fields, so
     * only a withheld profile drops them — which makes it the one identifiable cause.
     */
    protected function read(Response $response): PlayerBadges|ProfileNotPublicException
    {
        /**
         * @var array{response: array{badges?: list<array{
         *     badgeid: int,
         *     appid?: int,
         *     level: int,
         *     completion_time: int,
         *     xp: int,
         *     communityitemid?: string,
         *     border_color?: int,
         *     scarcity: int,
         * }>, player_xp: int, player_level?: int, player_xp_needed_to_level_up: int, player_xp_needed_current_level: int}} $body
         */
        $body = $response->json();

        if (! array_key_exists('player_level', $body['response'])) {
            return ProfileNotPublicException::forSteamId($this->steamId, $response);
        }

        return PlayerBadges::fromArray($body['response']);
    }

    protected function defaultQuery(): array
    {
        return [
            'steamid' => $this->steamId->value,
        ];
    }
}
