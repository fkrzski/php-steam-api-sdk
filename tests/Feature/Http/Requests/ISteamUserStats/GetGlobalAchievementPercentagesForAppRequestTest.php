<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\GlobalAchievement;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetGlobalAchievementPercentagesForAppRequest::class, GlobalAchievement::class, StatsUnavailableException::class]);

/**
 * @return list<GlobalAchievement>
 */
function sendGlobalAchievementsFixture(string $fixture, int $gameId = 381210): array
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetGlobalAchievementPercentagesForAppRequest::class => MockResponse::fixture(
            sprintf('ISteamUserStats/GetGlobalAchievementPercentagesForApp/%s', $fixture),
        ),
    ]));

    /** @var list<GlobalAchievement> $achievements */
    $achievements = $connector->send(new GetGlobalAchievementPercentagesForAppRequest($gameId))->dto();

    return $achievements;
}

test('endpoint targets GetGlobalAchievementPercentagesForApp v2', function (): void {
    $request = new GetGlobalAchievementPercentagesForAppRequest(381210);

    expect($request->resolveEndpoint())->toBe('/ISteamUserStats/GetGlobalAchievementPercentagesForApp/v2/');
});

test('query carries gameid rather than appid', function (): void {
    $request = new GetGlobalAchievementPercentagesForAppRequest(381210);

    expect($request->query()->all())->toBe(['gameid' => 381210]);
});

test('fixture response maps every achievement to the DTO', function (): void {
    $achievements = sendGlobalAchievementsFixture('default');

    expect($achievements)->toHaveCount(4)
        ->and($achievements[0])->toBeInstanceOf(GlobalAchievement::class)
        ->and($achievements[0]->apiName)->toBe('ACH_BLOODWEB_LVL10')
        ->and($achievements[3]->apiName)->toBe('NEW_ACHIEVEMENT_334_7');
});

test('percentages arrive as strings and become floats', function (): void {
    $achievements = sendGlobalAchievementsFixture('default');

    expect($achievements[0]->percent)->toBe(63.0)
        ->and($achievements[2]->percent)->toBe(57.8)
        ->and($achievements[3]->percent)->toBe(0.1);
});

test('an app with no global achievements throws StatsUnavailableException', function (): void {
    sendGlobalAchievementsFixture('no-achievements', 2270);
})->throws(
    StatsUnavailableException::class,
    'Steam returned no global achievements for game 2270: it carries none, or no game has that ID.',
);

test('an unknown game id is indistinguishable from one carrying no achievements', function (): void {
    sendGlobalAchievementsFixture('no-achievements', 999999999);
})->throws(
    StatsUnavailableException::class,
    'Steam returned no global achievements for game 999999999: it carries none, or no game has that ID.',
);

test('a failure other than 403 is left to the connector', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetGlobalAchievementPercentagesForAppRequest::class => MockResponse::make([], 500),
    ]));

    $connector->send(new GetGlobalAchievementPercentagesForAppRequest(381210));
})->throws(
    SteamApiException::class,
    'Steam API request failed with HTTP 500.',
);
