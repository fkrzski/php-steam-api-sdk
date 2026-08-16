<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\PlayerAchievement;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetPlayerAchievementsRequest::class, PlayerAchievements::class, PlayerAchievement::class, StatsUnavailableException::class]);

function playerAchievementsConnector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

function playerAchievementsSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198148125221');
}

test('endpoint targets GetPlayerAchievements v1', function (): void {
    $request = new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210);

    expect($request->resolveEndpoint())->toBe('/ISteamUserStats/GetPlayerAchievements/v1/');
});

test('query includes steamid and appid', function (): void {
    $request = new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210);

    expect($request->query()->all())->toMatchArray([
        'steamid' => '76561198148125221',
        'appid' => 381210,
    ]);
});

test('query includes language when provided', function (): void {
    $request = new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210, 'pl');

    expect($request->query()->all())->toMatchArray(['l' => 'pl']);
});

test('query omits language when null', function (): void {
    $request = new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210);

    expect($request->query()->all())->not->toHaveKey('l');
});

test('fixture response parses into PlayerAchievements DTO', function (): void {
    $mock = new MockClient([
        GetPlayerAchievementsRequest::class => MockResponse::fixture('ISteamUserStats/GetPlayerAchievements/default'),
    ]);

    $connector = playerAchievementsConnector();
    $connector->withMockClient($mock);

    /** @var PlayerAchievements $dto */
    $dto = $connector->send(new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210))->dto();

    expect($dto)->toBeInstanceOf(PlayerAchievements::class)
        ->and($dto->steamId->value)->toBe('76561198148125221')
        ->and($dto->gameName)->toBe('Dead by Daylight')
        ->and($dto->achievements)->toHaveCount(2)
        ->and($dto->achievements[0])->toBeInstanceOf(PlayerAchievement::class)
        ->and($dto->achievements[0]->apiName)->toBe('ACH_SACRIFICE_4_SURVIVORS_IAM')
        ->and($dto->achievements[0]->achieved)->toBeTrue()
        ->and($dto->achievements[0]->unlockedAt)->not->toBeNull()
        ->and($dto->achievements[0]->unlockedAt->getTimestamp())->toBe(1514326953);
});

test('achieved=0 and unlocktime=0 map to false and null', function (): void {
    $mock = new MockClient([
        GetPlayerAchievementsRequest::class => MockResponse::fixture('ISteamUserStats/GetPlayerAchievements/default'),
    ]);

    $connector = playerAchievementsConnector();
    $connector->withMockClient($mock);

    /** @var PlayerAchievements $dto */
    $dto = $connector->send(new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210))->dto();

    expect($dto->achievements[1]->achieved)->toBeFalse()
        ->and($dto->achievements[1]->unlockedAt)->toBeNull();
});

test('localized fixture populates name and description', function (): void {
    $mock = new MockClient([
        GetPlayerAchievementsRequest::class => MockResponse::fixture('ISteamUserStats/GetPlayerAchievements/localized'),
    ]);

    $connector = playerAchievementsConnector();
    $connector->withMockClient($mock);

    /** @var PlayerAchievements $dto */
    $dto = $connector->send(new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210, 'english'))->dto();

    expect($dto->achievements[0]->name)->toBe('I Am Inevitable')
        ->and($dto->achievements[0]->description)->toBe('Sacrifice 4 Survivors in a single match.');
});

test('achievement without localized data has null name and description', function (): void {
    $mock = new MockClient([
        GetPlayerAchievementsRequest::class => MockResponse::fixture('ISteamUserStats/GetPlayerAchievements/default'),
    ]);

    $connector = playerAchievementsConnector();
    $connector->withMockClient($mock);

    /** @var PlayerAchievements $dto */
    $dto = $connector->send(new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210))->dto();

    expect($dto->achievements[0]->name)->toBeNull()
        ->and($dto->achievements[0]->description)->toBeNull();
});

test('a 400 caused by a missing key is left to the connector, not read as a bad appid', function (): void {
    $mock = new MockClient([
        GetPlayerAchievementsRequest::class => MockResponse::fixture('Errors/missing-key'),
    ]);

    $connector = playerAchievementsConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210))->dto();
})->throws(InvalidApiKeyException::class, 'Steam API key is missing. Check the key passed to SteamConfig.');

test('a private profile and an app without stats are reported identically', function (): void {
    $mock = new MockClient([
        GetPlayerAchievementsRequest::class => MockResponse::fixture('ISteamUserStats/GetPlayerAchievements/stats-unavailable'),
    ]);

    $connector = playerAchievementsConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetPlayerAchievementsRequest(playerAchievementsSteamId(), 381210))->dto();
})->throws(StatsUnavailableException::class, 'Steam returned no stats for app 381210: it exposes none, or the profile is private.');
