<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetNumberOfCurrentPlayersRequest::class, AppNotFoundException::class]);

function sendCurrentPlayersFixture(string $fixture, int $appId = 381210): int
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture(
            sprintf('ISteamUserStats/GetNumberOfCurrentPlayers/%s', $fixture),
        ),
    ]));

    /** @var int $count */
    $count = $connector->send(new GetNumberOfCurrentPlayersRequest($appId))->dto();

    return $count;
}

test('endpoint targets GetNumberOfCurrentPlayers v1', function (): void {
    $request = new GetNumberOfCurrentPlayersRequest(381210);

    expect($request->resolveEndpoint())->toBe('/ISteamUserStats/GetNumberOfCurrentPlayers/v1/');
});

test('query carries appid parameter', function (): void {
    $request = new GetNumberOfCurrentPlayersRequest(381210);

    expect($request->query()->all())->toBe(['appid' => 381210]);
});

test('fixture response yields the player count as an int', function (): void {
    expect(sendCurrentPlayersFixture('default'))->toBe(40008);
});

test('app id zero yields the Steam-wide concurrent total', function (): void {
    expect(sendCurrentPlayersFixture('steam-wide', 0))->toBe(24453426);
});

test('unknown app throws AppNotFoundException', function (): void {
    sendCurrentPlayersFixture('unknown-app', 999999999);
})->throws(
    AppNotFoundException::class,
    'No Steam app found for app ID 999999999.',
);

test('a failure other than 404 is left to the connector', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 500),
    ]));

    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));
})->throws(
    SteamApiException::class,
    'Steam API request failed with HTTP 500.',
);
