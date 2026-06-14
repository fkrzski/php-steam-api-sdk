<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetOwnedGamesRequest::class, OwnedGame::class]);

function ownedGamesConnector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

function testSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

test('endpoint targets GetOwnedGames v1', function (): void {
    $request = new GetOwnedGamesRequest(testSteamId());

    expect($request->resolveEndpoint())->toBe('/IPlayerService/GetOwnedGames/v1/');
});

test('query includes steamid', function (): void {
    $request = new GetOwnedGamesRequest(testSteamId());

    expect($request->query()->all())->toMatchArray([
        'steamid' => '76561198000000000',
    ]);
});

test('query includes appids_filter when provided', function (): void {
    $request = new GetOwnedGamesRequest(testSteamId(), [381210]);

    expect($request->query()->all())->toMatchArray([
        'appids_filter' => [381210],
    ]);
});

test('query omits appids_filter when empty', function (): void {
    $request = new GetOwnedGamesRequest(testSteamId(), []);

    expect($request->query()->all())->not->toHaveKey('appids_filter');
});

test('query includes optional flags when true', function (): void {
    $request = new GetOwnedGamesRequest(testSteamId(), [], true, true);

    expect($request->query()->all())
        ->toMatchArray(['include_appinfo' => 1, 'include_played_free_games' => 1]);
});

test('query omits optional flags by default', function (): void {
    $request = new GetOwnedGamesRequest(testSteamId());

    expect($request->query()->all())
        ->not->toHaveKey('include_appinfo')
        ->not->toHaveKey('include_played_free_games');
});

test('OwnedGame defaults hasCommunityVisibleStats to false when key absent', function (): void {
    $game = OwnedGame::fromArray([
        'appid' => 1,
        'playtime_forever' => 0,
    ]);

    expect($game->hasCommunityVisibleStats)->toBeFalse();
});

test('fixture response parses into OwnedGame DTOs', function (): void {
    $mock = new MockClient([
        GetOwnedGamesRequest::class => MockResponse::fixture('get_owned_games'),
    ]);

    $connector = ownedGamesConnector();
    $connector->withMockClient($mock);

    /** @var list<OwnedGame> $dtos */
    $dtos = $connector->send(new GetOwnedGamesRequest(testSteamId(), [381210]))->dto();

    expect($dtos)->toHaveCount(1)
        ->and($dtos[0])->toBeInstanceOf(OwnedGame::class)
        ->and($dtos[0]->appId)->toBe(381210)
        ->and($dtos[0]->playtimeForever)->toBe(12345)
        ->and($dtos[0]->playtimeTwoWeeks)->toBe(120)
        ->and($dtos[0]->name)->toBe('Dead by Daylight')
        ->and($dtos[0]->imgIconUrl)->toBe('95be6d131fc61f145797317ca437c9765f24b41c')
        ->and($dtos[0]->hasCommunityVisibleStats)->toBeTrue();
});

test('not owned fixture returns empty list', function (): void {
    $mock = new MockClient([
        GetOwnedGamesRequest::class => MockResponse::fixture('get_owned_games_not_owned'),
    ]);

    $connector = ownedGamesConnector();
    $connector->withMockClient($mock);

    $dtos = $connector->send(new GetOwnedGamesRequest(testSteamId(), [381210]))->dto();

    expect($dtos)->toBeEmpty();
});

test('private profile throws ProfileNotPublicException', function (): void {
    $mock = new MockClient([
        GetOwnedGamesRequest::class => MockResponse::fixture('get_owned_games_private'),
    ]);

    $connector = ownedGamesConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetOwnedGamesRequest(testSteamId()))->dto();
})->throws(ProfileNotPublicException::class, 'Steam profile is not public.');
