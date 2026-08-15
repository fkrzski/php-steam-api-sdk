<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetUserGroupListRequest::class, UserGroup::class]);

function userGroupListConnector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

function userGroupTestSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

test('endpoint targets GetUserGroupList v1', function (): void {
    $request = new GetUserGroupListRequest(userGroupTestSteamId());

    expect($request->resolveEndpoint())->toBe('/ISteamUser/GetUserGroupList/v1/');
});

test('query includes steamid', function (): void {
    $request = new GetUserGroupListRequest(userGroupTestSteamId());

    expect($request->query()->all())->toBe([
        'steamid' => '76561198000000000',
    ]);
});

test('fixture response parses into UserGroup DTOs', function (): void {
    $mock = new MockClient([
        GetUserGroupListRequest::class => MockResponse::fixture('ISteamUser/GetUserGroupList/default'),
    ]);

    $connector = userGroupListConnector();
    $connector->withMockClient($mock);

    /** @var list<UserGroup> $dtos */
    $dtos = $connector->send(new GetUserGroupListRequest(userGroupTestSteamId()))->dto();

    expect($dtos)->toHaveCount(4)
        ->and($dtos[0])->toBeInstanceOf(UserGroup::class)
        ->and($dtos[0]->gid)->toBe('4218398')
        ->and($dtos[3]->gid)->toBe('25392543');
});

test('membership-free fixture returns empty list', function (): void {
    $mock = new MockClient([
        GetUserGroupListRequest::class => MockResponse::fixture('ISteamUser/GetUserGroupList/empty'),
    ]);

    $connector = userGroupListConnector();
    $connector->withMockClient($mock);

    $dtos = $connector->send(new GetUserGroupListRequest(userGroupTestSteamId()))->dto();

    expect($dtos)->toBeEmpty();
});

test('response without a success flag throws ProfileNotPublicException', function (): void {
    $mock = new MockClient([
        GetUserGroupListRequest::class => MockResponse::make(['response' => []]),
    ]);

    $connector = userGroupListConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetUserGroupListRequest(userGroupTestSteamId()))->dto();
})->throws(ProfileNotPublicException::class, 'Steam profile is not public.');

test('private profile throws ProfileNotPublicException', function (): void {
    $mock = new MockClient([
        GetUserGroupListRequest::class => MockResponse::fixture('ISteamUser/GetUserGroupList/private'),
    ]);

    $connector = userGroupListConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetUserGroupListRequest(userGroupTestSteamId()))->dto();
})->throws(ProfileNotPublicException::class, 'Steam profile is not public.');
