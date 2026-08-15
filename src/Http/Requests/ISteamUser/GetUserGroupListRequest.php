<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUser;

use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetUserGroupListRequest extends Request
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

    /**
     * @return list<UserGroup>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /**
         * @var array{response?: array{
         *     success?: bool,
         *     error?: string,
         *     groups?: list<array{gid: string}>,
         * }} $body
         */
        $body = $response->json();
        $responseBody = $body['response'] ?? [];

        if (($responseBody['success'] ?? null) !== true) {
            throw new ProfileNotPublicException('Steam profile is not public.');
        }

        return array_map(UserGroup::fromArray(...), $responseBody['groups'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return ['steamid' => $this->steamId->value];
    }
}
