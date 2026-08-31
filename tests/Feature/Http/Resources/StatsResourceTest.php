<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\GlobalAchievement;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(StatsResource::class);

function statsResourceSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198148125221');
}

function statsResourceMock(string $requestClass, string $fixture): MockClient
{
    return new MockClient([$requestClass => MockResponse::fixture($fixture)]);
}

function statsResource(MockClient $mockClient): StatsResource
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    return $connector->stats();
}

test('achievements sends GetPlayerAchievements and returns the DTO', function (): void {
    $mockClient = statsResourceMock(
        GetPlayerAchievementsRequest::class,
        'ISteamUserStats/GetPlayerAchievements/default',
    );

    $achievements = statsResource($mockClient)->achievements(statsResourceSteamId(), 381210);

    expect($achievements)->toBeInstanceOf(PlayerAchievements::class)
        ->and($achievements->gameName)->toBe('Dead by Daylight')
        ->and($achievements->achievements)->toHaveCount(2)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe([
            'steamid' => '76561198148125221',
            'appid' => 381210,
        ]);
});

test('achievements forwards the language', function (): void {
    $mockClient = statsResourceMock(
        GetPlayerAchievementsRequest::class,
        'ISteamUserStats/GetPlayerAchievements/localized',
    );

    statsResource($mockClient)->achievements(statsResourceSteamId(), 381210, Language::Polish);

    expect($mockClient->getLastRequest()?->query()->all())->toBe([
        'steamid' => '76561198148125221',
        'appid' => 381210,
        'l' => 'polish',
    ]);
});

test('currentPlayers sends GetNumberOfCurrentPlayers and returns the count', function (): void {
    $mockClient = statsResourceMock(
        GetNumberOfCurrentPlayersRequest::class,
        'ISteamUserStats/GetNumberOfCurrentPlayers/default',
    );

    $count = statsResource($mockClient)->currentPlayers(381210);

    expect($count)->toBe(40008)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['appid' => 381210]);
});

test('globalAchievements sends GetGlobalAchievementPercentagesForApp and returns the DTOs', function (): void {
    $mockClient = statsResourceMock(
        GetGlobalAchievementPercentagesForAppRequest::class,
        'ISteamUserStats/GetGlobalAchievementPercentagesForApp/default',
    );

    $achievements = statsResource($mockClient)->globalAchievements(gameId: 381210);

    expect($achievements)->toHaveCount(4)
        ->and($achievements[0])->toBeInstanceOf(GlobalAchievement::class)
        ->and($achievements[0]->apiName)->toBe('ACH_BLOODWEB_LVL10')
        ->and($achievements[0]->percent)->toBe(63.0)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['gameid' => 381210]);
});

test('schema sends GetSchemaForGame and returns the DTO', function (): void {
    $mockClient = statsResourceMock(
        GetSchemaForGameRequest::class,
        'ISteamUserStats/GetSchemaForGame/default',
    );

    $schema = statsResource($mockClient)->schema(381210);

    expect($schema)->toBeInstanceOf(GameSchema::class)
        ->and($schema->gameName)->toBe('Dead by Daylight')
        ->and($schema->achievements)->toHaveCount(3)
        ->and($schema->stats)->toHaveCount(3)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['appid' => 381210]);
});

test('schema forwards the language', function (): void {
    $mockClient = statsResourceMock(
        GetSchemaForGameRequest::class,
        'ISteamUserStats/GetSchemaForGame/localized',
    );

    statsResource($mockClient)->schema(381210, Language::Polish);

    expect($mockClient->getLastRequest()?->query()->all())->toBe([
        'appid' => 381210,
        'l' => 'polish',
    ]);
});

test('userStats sends GetUserStatsForGame and returns the DTO', function (): void {
    $mockClient = statsResourceMock(
        GetUserStatsForGameRequest::class,
        'ISteamUserStats/GetUserStatsForGame/default',
    );

    $stats = statsResource($mockClient)->userStats(statsResourceSteamId(), 381210);

    expect($stats)->toBeInstanceOf(UserStats::class)
        ->and($stats->gameName)->toBe('Dead by Daylight')
        ->and($stats->stats)->toHaveCount(2)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe([
            'steamid' => '76561198148125221',
            'appid' => 381210,
        ]);
});

test('userStats forwards the language', function (): void {
    $mockClient = statsResourceMock(
        GetUserStatsForGameRequest::class,
        'ISteamUserStats/GetUserStatsForGame/default',
    );

    statsResource($mockClient)->userStats(statsResourceSteamId(), 381210, Language::Polish);

    expect($mockClient->getLastRequest()?->query()->all())->toBe([
        'steamid' => '76561198148125221',
        'appid' => 381210,
        'l' => 'polish',
    ]);
});
