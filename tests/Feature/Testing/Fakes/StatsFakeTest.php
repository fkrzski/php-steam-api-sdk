<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GameSchemaFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GlobalAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatsFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\StatsFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Request;

covers(StatsFake::class);

// Envelopes

test('nests user stats under the playerstats key', function (): void {
    $answer = answerFrom(new SteamFake()->stats()->userStats(UserStatsFactory::new()), new GetUserStatsForGameRequest(steamId(), 381210));

    expect($answer->body())->toBe(json_encode([
        'playerstats' => UserStatsFactory::new()->toArray(),
    ], JSON_THROW_ON_ERROR));
});

test('nests player achievements under the playerstats key', function (): void {
    $answer = answerFrom(
        new SteamFake()->stats()->achievements(PlayerAchievementsFactory::new()),
        new GetPlayerAchievementsRequest(steamId(), 381210),
    );

    expect($answer->body())->toBe(json_encode([
        'playerstats' => PlayerAchievementsFactory::new()->toArray(),
    ], JSON_THROW_ON_ERROR));
});

test('nests global achievements under the achievementpercentages key', function (): void {
    $answer = answerFrom(
        new SteamFake()->stats()->globalAchievements(
            GlobalAchievementFactory::new(),
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
        ),
        new GetGlobalAchievementPercentagesForAppRequest(381210),
    );

    expect($answer->body())->toBe(json_encode([
        'achievementpercentages' => [
            'achievements' => [
                GlobalAchievementFactory::new()->toArray(),
                GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5)->toArray(),
            ],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('nests the game schema under the game key', function (): void {
    $answer = answerFrom(new SteamFake()->stats()->schema(GameSchemaFactory::new()), new GetSchemaForGameRequest(620));

    expect($answer->body())->toBe(json_encode([
        'game' => GameSchemaFactory::new()->toArray(),
    ], JSON_THROW_ON_ERROR));
});

test('sends the body steam was captured sending', function (Closure $fake, Request $request, string $fixture): void {
    $answer = answerFrom($fake(new SteamFake), $request);

    expect([$answer->status(), $answer->body()])->toBe(captured($fixture));
})->with([
    'userStatsNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->userStatsNotPublic(),
        new GetUserStatsForGameRequest(steamId(), 381210),
        'ISteamUserStats/GetUserStatsForGame/private',
    ],
    'achievementsUnavailable()' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->achievementsUnavailable(),
        new GetPlayerAchievementsRequest(steamId(), 381210),
        'ISteamUserStats/GetPlayerAchievements/stats-unavailable',
    ],
    'currentPlayersAppNotFound()' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->currentPlayersAppNotFound(),
        new GetNumberOfCurrentPlayersRequest(1),
        'ISteamUserStats/GetNumberOfCurrentPlayers/unknown-app',
    ],
    'globalAchievementsUnavailable()' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->globalAchievementsUnavailable(),
        new GetGlobalAchievementPercentagesForAppRequest(381210),
        'ISteamUserStats/GetGlobalAchievementPercentagesForApp/no-achievements',
    ],
    'schemaAppNotFound()' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->schemaAppNotFound(),
        new GetSchemaForGameRequest(1),
        'ISteamUserStats/GetSchemaForGame/unknown-app',
    ],
    'currentPlayers()' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->currentPlayers(40008),
        new GetNumberOfCurrentPlayersRequest(730),
        'ISteamUserStats/GetNumberOfCurrentPlayers/default',
    ],
    'schema() of an empty schema' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->schema(GameSchemaFactory::new()->empty()),
        new GetSchemaForGameRequest(480),
        'ISteamUserStats/GetSchemaForGame/empty',
    ],
]);

// Round trips through the connector

test('fakes every stats endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->stats()->userStats(UserStatsFactory::new()->gameName('Dead by Daylight'))
        ->stats()->achievements(PlayerAchievementsFactory::new()->achievements(
            PlayerAchievementFactory::new(),
            PlayerAchievementFactory::new()->locked(),
        ))
        ->stats()->currentPlayers(12_345)
        ->stats()->globalAchievements(
            GlobalAchievementFactory::new(),
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
        )
        ->stats()->schema(GameSchemaFactory::new()->gameName('Portal 2'));
    $connector = fakedConnector($fake);

    $userStats = $connector->stats()->userStats(steamId(), appId: 381210);
    $globalAchievements = $connector->stats()->globalAchievements(gameId: 381210);
    $schema = $connector->stats()->schema(appId: 620);

    expect($userStats->gameName)->toBe('Dead by Daylight')
        ->and($userStats->stats)->toHaveCount(1)
        ->and($connector->stats()->achievements(steamId(), appId: 381210)->achievements)->toHaveCount(2)
        ->and($connector->stats()->currentPlayers(appId: 381210))->toBe(12_345)
        ->and($globalAchievements)->toHaveCount(2)
        ->and($globalAchievements[1]->apiName)->toBe('ACH_ESCAPE')
        ->and($globalAchievements[1]->percent)->toBe(12.5)
        ->and($schema->gameName)->toBe('Portal 2')
        ->and($schema->stats)->toHaveCount(1)
        ->and($schema->achievements)->toHaveCount(1);

    $fake->assertSentCount(5);
});

// Failures — these pin the contract with the SDK's exception mapping

test('raises what the sdk raises for a named failure', function (Closure $fail, Closure $call, string $exception): void {
    $fake = new SteamFake;
    $connector = fakedConnector($fake);

    expect($fail($fake))->toBe($fake)
        ->and(fn (): mixed => $call($connector))->toThrow($exception);
})->with([
    'user stats not public' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->userStatsNotPublic(),
        fn (SteamConnector $connector): UserStats => $connector->stats()->userStats(steamId(), appId: 381210),
        ProfileNotPublicException::class,
    ],
    'achievements unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->achievementsUnavailable(),
        fn (SteamConnector $connector): PlayerAchievements => $connector->stats()->achievements(steamId(), appId: 381210),
        StatsUnavailableException::class,
    ],
    'current players app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->currentPlayersAppNotFound(),
        fn (SteamConnector $connector): int => $connector->stats()->currentPlayers(appId: 1),
        AppNotFoundException::class,
    ],
    'global achievements unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->globalAchievementsUnavailable(),
        fn (SteamConnector $connector): array => $connector->stats()->globalAchievements(gameId: 381210),
        StatsUnavailableException::class,
    ],
    'schema app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->schemaAppNotFound(),
        fn (SteamConnector $connector): GameSchema => $connector->stats()->schema(appId: 1),
        AppNotFoundException::class,
    ],
]);
