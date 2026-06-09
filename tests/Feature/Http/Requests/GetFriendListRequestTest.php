<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Http\Requests\GetFriendListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetFriendListRequest::class, Friend::class]);

function friendListConnector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

function friendTestSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

test('endpoint targets GetFriendList v1', function (): void {
    $request = new GetFriendListRequest(friendTestSteamId());

    expect($request->resolveEndpoint())->toBe('/ISteamUser/GetFriendList/v1/');
});

test('query includes steamid', function (): void {
    $request = new GetFriendListRequest(friendTestSteamId());

    expect($request->query()->all())->toMatchArray([
        'steamid' => '76561198000000000',
    ]);
});

test('query includes relationship when provided', function (FriendRelationship $relationship, string $expected): void {
    $request = new GetFriendListRequest(friendTestSteamId(), $relationship);

    expect($request->query()->all())->toMatchArray(['relationship' => $expected]);
})->with([
    'all' => [FriendRelationship::All, 'all'],
    'friend' => [FriendRelationship::Friend, 'friend'],
]);

test('query omits relationship by default', function (): void {
    $request = new GetFriendListRequest(friendTestSteamId());

    expect($request->query()->all())->not->toHaveKey('relationship');
});

test('fixture response parses into Friend DTOs', function (): void {
    $mock = new MockClient([
        GetFriendListRequest::class => MockResponse::fixture('get_friend_list'),
    ]);

    $connector = friendListConnector();
    $connector->withMockClient($mock);

    /** @var list<Friend> $dtos */
    $dtos = $connector->send(new GetFriendListRequest(friendTestSteamId(), FriendRelationship::Friend))->dto();

    expect($dtos)->toHaveCount(4)
        ->and($dtos[0])->toBeInstanceOf(Friend::class)
        ->and($dtos[0]->steamId)->toBeInstanceOf(SteamId::class)
        ->and($dtos[0]->steamId->value)->toBe('76561198000408055')
        ->and($dtos[0]->relationship)->toBe(FriendRelationship::Friend)
        ->and($dtos[0]->friendSince->getTimestamp())->toBe(1750068880);
});

test('empty or private fixture returns empty list', function (): void {
    $mock = new MockClient([
        GetFriendListRequest::class => MockResponse::fixture('get_friend_list_empty'),
    ]);

    $connector = friendListConnector();
    $connector->withMockClient($mock);

    $dtos = $connector->send(new GetFriendListRequest(friendTestSteamId()))->dto();

    expect($dtos)->toBeEmpty();
});
