<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUser;

use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<list<UserGroup>>
 */
final class GetUserGroupListRequest extends SteamApiRequest
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUser/GetUserGroupList/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['response'];
    }

    /**
     * @return list<UserGroup>
     */
    protected function read(Response $response): array
    {
        /** @var array{response?: array{groups?: list<array{gid: string}>}} $body */
        $body = $response->json();

        return array_map(UserGroup::fromArray(...), $body['response']['groups'] ?? []);
    }

    protected function defaultQuery(): array
    {
        return ['steamid' => $this->steamId->value];
    }
}
