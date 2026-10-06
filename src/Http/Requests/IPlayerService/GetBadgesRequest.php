<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class GetBadgesRequest extends Request
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

    /**
     * A SteamID64 that belongs to no account still gets the zeroed progress fields, so
     * only a withheld profile drops them — which makes it the one identifiable cause.
     */
    #[Override]
    public function hasRequestFailed(Response $response): ?bool
    {
        // No opinion off a 200: false would overrule Saloon's own 4xx and 5xx check.
        if ($response->status() !== 200) {
            return null;
        }

        /** @var array{response?: array{player_level?: int}} $body */
        $body = $response->json();

        return ! array_key_exists('player_level', $body['response'] ?? []);
    }

    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->hasRequestFailed($response) === true
            ? ProfileNotPublicException::forSteamId($this->steamId, $response)
            : null;
    }

    public function createDtoFromResponse(Response $response): PlayerBadges
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
         * }>, player_xp: int, player_level: int, player_xp_needed_to_level_up: int, player_xp_needed_current_level: int}} $body
         */
        $body = $response->json();

        return PlayerBadges::fromArray($body['response']);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'steamid' => $this->steamId->value,
        ];
    }
}
