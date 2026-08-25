<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(UsersResource::class);

function usersResourceSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function usersResourceMock(string $requestClass, string $fixture): MockClient
{
    return new MockClient([$requestClass => MockResponse::fixture($fixture)]);
}

function usersResource(MockClient $mockClient): UsersResource
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    return $connector->users();
}

test('summaries sends GetPlayerSummaries and returns PlayerSummary DTOs', function (): void {
    $mockClient = usersResourceMock(GetPlayerSummariesRequest::class, 'ISteamUser/GetPlayerSummaries/default');

    $summaries = usersResource($mockClient)->summaries([usersResourceSteamId()]);

    expect($summaries)->toHaveCount(2)
        ->and($summaries[0])->toBeInstanceOf(PlayerSummary::class)
        ->and($summaries[0]->personaName)->toBe('tester-one')
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['steamids' => '76561198000000000']);
});

test('bans sends GetPlayerBans and returns PlayerBan DTOs', function (): void {
    $mockClient = usersResourceMock(GetPlayerBansRequest::class, 'ISteamUser/GetPlayerBans/default');

    $bans = usersResource($mockClient)->bans([usersResourceSteamId()]);

    expect($bans)->toHaveCount(2)
        ->and($bans[0])->toBeInstanceOf(PlayerBan::class)
        ->and($bans[1]->isVacBanned)->toBeTrue()
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['steamids' => '76561198000000000']);
});

test('friends sends GetFriendList and returns Friend DTOs', function (): void {
    $mockClient = usersResourceMock(GetFriendListRequest::class, 'ISteamUser/GetFriendList/default');

    $friends = usersResource($mockClient)->friends(usersResourceSteamId());

    expect($friends)->toHaveCount(4)
        ->and($friends[0])->toBeInstanceOf(Friend::class)
        ->and($friends[0]->steamId->value)->toBe('76561198000408055')
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['steamid' => '76561198000000000']);
});

test('friends forwards the relationship filter', function (): void {
    $mockClient = usersResourceMock(GetFriendListRequest::class, 'ISteamUser/GetFriendList/default');

    usersResource($mockClient)->friends(usersResourceSteamId(), FriendRelationship::Friend);

    expect($mockClient->getLastRequest()?->query()->all())->toBe([
        'steamid' => '76561198000000000',
        'relationship' => 'friend',
    ]);
});

test('groups sends GetUserGroupList and returns UserGroup DTOs', function (): void {
    $mockClient = usersResourceMock(GetUserGroupListRequest::class, 'ISteamUser/GetUserGroupList/default');

    $groups = usersResource($mockClient)->groups(usersResourceSteamId());

    expect($groups)->toHaveCount(4)
        ->and($groups[0])->toBeInstanceOf(UserGroup::class)
        ->and($groups[0]->gid)->toBe('4218398');
});

test('resolveVanityUrl sends ResolveVanityURL and returns a SteamId', function (): void {
    $mockClient = usersResourceMock(ResolveVanityUrlRequest::class, 'ISteamUser/ResolveVanityUrl/success');

    $steamId = usersResource($mockClient)->resolveVanityUrl('gabelogannewell');

    expect($steamId)->toBeInstanceOf(SteamId::class)
        ->and($steamId->value)->toBe('76561198000000000')
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['vanityurl' => 'gabelogannewell']);
});
