<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUser;

use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetFriendListRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
        public readonly ?FriendRelationship $relationship = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUser/GetFriendList/v1/';
    }

    /**
     * @return list<Friend>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /**
         * @var array{friendslist?: array{friends?: list<array{
         *     steamid: string,
         *     relationship: string,
         *     friend_since: int,
         * }>}} $body
         */
        $body = $response->json();

        return array_map(Friend::fromArray(...), $body['friendslist']['friends'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = ['steamid' => $this->steamId->value];

        if ($this->relationship instanceof FriendRelationship) {
            $query['relationship'] = $this->relationship->value;
        }

        return $query;
    }
}
