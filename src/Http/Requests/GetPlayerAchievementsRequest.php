<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests;

use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetPlayerAchievementsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
        public readonly int $appId,
        public readonly ?string $language = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUserStats/GetPlayerAchievements/v1/';
    }

    public function createDtoFromResponse(Response $response): PlayerAchievements
    {
        /**
         * @var array{playerstats?: array{
         *     steamID: string,
         *     gameName: string,
         *     achievements: list<array{apiname: string, achieved: int, unlocktime: int, name?: string, description?: string}>,
         *     success: bool,
         * }} $body
         */
        $body = $response->json();

        if (! isset($body['playerstats']['success']) || $body['playerstats']['success'] === false) {
            throw new ProfileNotPublicException('Steam profile is not public.');
        }

        return PlayerAchievements::fromArray($body['playerstats']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [
            'steamid' => $this->steamId->value,
            'appid' => $this->appId,
        ];

        if ($this->language !== null) {
            $query['l'] = $this->language;
        }

        return $query;
    }
}
