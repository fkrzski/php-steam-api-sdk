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
use Throwable;

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

    #[Override]
    public function hasRequestFailed(Response $response): ?bool
    {
        // No opinion off a 200: false would overrule Saloon's own 4xx and 5xx check.
        if ($response->status() !== 200) {
            return null;
        }

        /** @var array{response?: array{quests?: list<mixed>}} $body */
        $body = $response->json();

        return ! isset($body['response']['quests']);
    }

    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->hasRequestFailed($response) === true
            ? ProfileNotPublicException::forPrivateOrMissing($this->steamId, $response)
            : null;
    }

    /**
     * @return list<CommunityBadgeQuest>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array{response: array{quests: list<array{questid: int, completed: bool}>}} $body */
        $body = $response->json();

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
