<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\UserStat;
use Fkrzski\SteamApiSdk\Dto\UserStatAchievement;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetUserStatsForGameRequest::class, UserStats::class, UserStat::class, UserStatAchievement::class]);

function userStatsConnector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

function userStatsSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198148125221');
}

test('endpoint targets GetUserStatsForGame v2', function (): void {
    $request = new GetUserStatsForGameRequest(userStatsSteamId(), 381210);

    expect($request->resolveEndpoint())->toBe('/ISteamUserStats/GetUserStatsForGame/v2/');
});

test('query includes steamid and appid', function (): void {
    $request = new GetUserStatsForGameRequest(userStatsSteamId(), 381210);

    expect($request->query()->all())->toMatchArray([
        'steamid' => '76561198148125221',
        'appid' => 381210,
    ]);
});

test('query includes language when provided', function (): void {
    $request = new GetUserStatsForGameRequest(userStatsSteamId(), 381210, Language::Polish);

    expect($request->query()->all())->toMatchArray(['l' => 'polish']);
});

test('query omits language when null', function (): void {
    $request = new GetUserStatsForGameRequest(userStatsSteamId(), 381210);

    expect($request->query()->all())->not->toHaveKey('l');
});

test('fixture response parses into UserStats DTO', function (): void {
    $mock = new MockClient([
        GetUserStatsForGameRequest::class => MockResponse::fixture('ISteamUserStats/GetUserStatsForGame/default'),
    ]);

    $connector = userStatsConnector();
    $connector->withMockClient($mock);

    /** @var UserStats $dto */
    $dto = $connector->send(new GetUserStatsForGameRequest(userStatsSteamId(), 381210))->dto();

    expect($dto)->toBeInstanceOf(UserStats::class)
        ->and($dto->steamId->value)->toBe('76561198148125221')
        ->and($dto->gameName)->toBe('Dead by Daylight')
        ->and($dto->stats)->toHaveCount(2)
        ->and($dto->stats[0])->toBeInstanceOf(UserStat::class)
        ->and($dto->stats[0]->name)->toBe('DBD_KillerSkulls')
        ->and($dto->stats[0]->value)->toBe(70)
        ->and($dto->achievements)->toHaveCount(2)
        ->and($dto->achievements[0])->toBeInstanceOf(UserStatAchievement::class)
        ->and($dto->achievements[0]->name)->toBe('ACH_SACRIFICE_4_SURVIVORS_IAM')
        ->and($dto->achievements[0]->achieved)->toBeTrue()
        ->and($dto->achievements[1]->achieved)->toBeFalse();
});

test('empty stats and achievements parse to empty lists', function (): void {
    $mock = new MockClient([
        GetUserStatsForGameRequest::class => MockResponse::fixture('ISteamUserStats/GetUserStatsForGame/empty'),
    ]);

    $connector = userStatsConnector();
    $connector->withMockClient($mock);

    /** @var UserStats $dto */
    $dto = $connector->send(new GetUserStatsForGameRequest(userStatsSteamId(), 381210))->dto();

    expect($dto->stats)->toBeEmpty()
        ->and($dto->achievements)->toBeEmpty();
});

test('private profile throws ProfileNotPublicException', function (): void {
    $mock = new MockClient([
        GetUserStatsForGameRequest::class => MockResponse::fixture('ISteamUserStats/GetUserStatsForGame/private'),
    ]);

    $connector = userStatsConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetUserStatsForGameRequest(userStatsSteamId(), 381210))->dto();
})->throws(ProfileNotPublicException::class, 'Steam profile 76561198148125221 is not public.');

test('a 403 from Steam maps to ProfileNotPublicException via the connector', function (): void {
    $mock = new MockClient([
        GetUserStatsForGameRequest::class => MockResponse::make(['playerstats' => ['success' => false]], 403),
    ]);

    $connector = userStatsConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetUserStatsForGameRequest(userStatsSteamId(), 381210))->dto();
})->throws(ProfileNotPublicException::class, 'Steam profile is not public.');

test('a 400 caused by a missing key is left to the connector', function (): void {
    $mock = new MockClient([
        GetUserStatsForGameRequest::class => MockResponse::fixture('Errors/missing-key'),
    ]);

    $connector = userStatsConnector();
    $connector->withMockClient($mock);

    $connector->send(new GetUserStatsForGameRequest(userStatsSteamId(), 381210))->dto();
})->throws(InvalidApiKeyException::class, 'Steam API key is missing. Check the key passed to SteamConfig.');
