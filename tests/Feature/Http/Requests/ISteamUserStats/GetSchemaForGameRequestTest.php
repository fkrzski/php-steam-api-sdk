<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\SchemaAchievement;
use Fkrzski\SteamApiSdk\Dto\SchemaStat;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetSchemaForGameRequest::class, GameSchema::class, SchemaAchievement::class, SchemaStat::class]);

function sendSchemaFixture(string $fixture, int $appId = 381210): GameSchema
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetSchemaForGameRequest::class => MockResponse::fixture(
            sprintf('ISteamUserStats/GetSchemaForGame/%s', $fixture),
        ),
    ]));

    /** @var GameSchema $schema */
    $schema = $connector->send(new GetSchemaForGameRequest($appId))->dto();

    return $schema;
}

test('endpoint targets GetSchemaForGame v2', function (): void {
    $request = new GetSchemaForGameRequest(381210);

    expect($request->resolveEndpoint())->toBe('/ISteamUserStats/GetSchemaForGame/v2/');
});

test('query includes appid', function (): void {
    $request = new GetSchemaForGameRequest(381210);

    expect($request->query()->all())->toMatchArray(['appid' => 381210]);
});

test('query includes language when provided', function (): void {
    $request = new GetSchemaForGameRequest(381210, Language::Polish);

    expect($request->query()->all())->toMatchArray(['l' => 'polish']);
});

test('query omits language when null', function (): void {
    $request = new GetSchemaForGameRequest(381210);

    expect($request->query()->all())->not->toHaveKey('l');
});

test('fixture response parses into the GameSchema DTO', function (): void {
    $schema = sendSchemaFixture('default');

    expect($schema)->toBeInstanceOf(GameSchema::class)
        ->and($schema->gameName)->toBe('Dead by Daylight')
        ->and($schema->gameVersion)->toBe('234')
        ->and($schema->stats)->toHaveCount(3)
        ->and($schema->achievements)->toHaveCount(3);
});

test('achievements carry the API name apart from the display name', function (): void {
    $achievement = sendSchemaFixture('default')->achievements[0];

    expect($achievement)->toBeInstanceOf(SchemaAchievement::class)
        ->and($achievement->apiName)->toBe('ACH_SACRIFICE_4_SURVIVORS_IAM')
        ->and($achievement->name)->toBe('The Grand Sacrifice')
        ->and($achievement->description)->toBe("In a public match, get\u{A0}4\u{A0}sacrifices in a single match.")
        ->and($achievement->hidden)->toBeFalse()
        ->and($achievement->icon)->toBe('https://steamcdn-a.akamaihd.net/steamcommunity/public/images/apps/381210/ecec7bc28a5d39088f1296c5f5a305ab53896754.jpg')
        ->and($achievement->iconGray)->toBe('https://steamcdn-a.akamaihd.net/steamcommunity/public/images/apps/381210/a652a10c283a58e926799e545ac47168b13cb053.jpg');
});

test('a hidden achievement keeps its name but has no description', function (): void {
    $achievement = sendSchemaFixture('default')->achievements[2];

    expect($achievement->hidden)->toBeTrue()
        ->and($achievement->name)->toBe('Blood on your hands')
        ->and($achievement->description)->toBeNull();
});

test('stats carry their default value', function (): void {
    $stats = sendSchemaFixture('default')->stats;

    expect($stats[0])->toBeInstanceOf(SchemaStat::class)
        ->and($stats[0]->apiName)->toBe('DBD_KillerSkulls')
        ->and($stats[0]->defaultValue)->toBe(0)
        ->and($stats[1]->apiName)->toBe('DBD_BloodwebMaxLevel')
        ->and($stats[1]->defaultValue)->toBe(1);
});

test('a stat Steam leaves unlabelled reads as a null name', function (): void {
    $stats = sendSchemaFixture('default')->stats;

    expect($stats[0]->name)->toBeNull();
});

test('a stat Steam does label keeps that label', function (): void {
    $stats = sendSchemaFixture('unnamed-game', 252490)->stats;

    expect($stats[0]->name)->toBe('Deaths')
        ->and($stats[1]->name)->toBeNull();
});

test('a blank game name reads the same as an absent one', function (): void {
    $schema = sendSchemaFixture('unnamed-game', 252490);

    expect($schema->gameName)->toBeNull()
        ->and($schema->gameVersion)->toBe('100');
});

test('the language reaches the payload', function (): void {
    $schema = sendSchemaFixture('localized');

    expect($schema->achievements[0]->name)->toBe('Wielka Ofiara')
        ->and($schema->achievements[0]->description)->toBe('Złóż w ofierze wszystkich czterech ocalałych; dokonaj tego podczas jednego meczu.');
});

test('a game carrying only achievements parses to an empty stat list', function (): void {
    $schema = sendSchemaFixture('achievements-only', 220);

    expect($schema->gameName)->toBe('Half-Life 2')
        ->and($schema->stats)->toBeEmpty()
        ->and($schema->achievements)->toHaveCount(2);
});

test('a game carrying only stats parses to an empty achievement list', function (): void {
    $schema = sendSchemaFixture('stats-only', 570);

    expect($schema->achievements)->toBeEmpty()
        ->and($schema->stats)->toHaveCount(1);
});

test('an app publishing no schema is not a failure', function (): void {
    $schema = sendSchemaFixture('empty', 250820);

    expect($schema->gameName)->toBeNull()
        ->and($schema->gameVersion)->toBeNull()
        ->and($schema->stats)->toBeEmpty()
        ->and($schema->achievements)->toBeEmpty();
});

test('an app id Steam does not know throws AppNotFoundException', function (): void {
    sendSchemaFixture('unknown-app', 999999999);
})->throws(AppNotFoundException::class, 'No Steam app found for app ID 999999999.');

test('a 400 caused by a missing key is left to the connector', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetSchemaForGameRequest::class => MockResponse::fixture('Errors/missing-key'),
    ]));

    $connector->send(new GetSchemaForGameRequest(381210));
})->throws(InvalidApiKeyException::class, 'Steam API key is missing. Check the key passed to SteamConfig.');

test('a failure other than 400 is left to the connector', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetSchemaForGameRequest::class => MockResponse::make([], 500),
    ]));

    $connector->send(new GetSchemaForGameRequest(381210));
})->throws(SteamApiException::class, 'Steam API request failed with HTTP 500.');
