<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetCommunityBadgeProgressRequest extends Request
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

    /**
     * @return list<CommunityBadgeQuest>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array{response?: array{quests?: list<array{questid: int, completed: bool}>}} $body */
        $body = $response->json();

        if (! isset($body['response']['quests'])) {
            throw ProfileNotPublicException::forPrivateOrMissing($this->steamId, $response);
        }

        return array_map(CommunityBadgeQuest::fromArray(...), $body['response']['quests']);
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
