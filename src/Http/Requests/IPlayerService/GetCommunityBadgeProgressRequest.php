<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<list<CommunityBadgeQuest>>
 */
final class GetCommunityBadgeProgressRequest extends SteamApiRequest
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/IPlayerService/GetCommunityBadgeProgress/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['response'];
    }

    /**
     * @return list<CommunityBadgeQuest>|ProfileNotPublicException
     */
    protected function read(Response $response): array|ProfileNotPublicException
    {
        /** @var array{response: array{quests?: list<array{questid: int, completed: bool}>}} $body */
        $body = $response->json();

        if (! isset($body['response']['quests'])) {
            return ProfileNotPublicException::forPrivateOrMissing($this->steamId, $response);
        }

        return array_map(CommunityBadgeQuest::fromArray(...), $body['response']['quests']);
    }

    protected function defaultQuery(): array
    {
        return [
            'steamid' => $this->steamId->value,
        ];
    }
}
