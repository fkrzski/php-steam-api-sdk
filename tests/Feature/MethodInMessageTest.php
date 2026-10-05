<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Enums\Method;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

covers([
    SteamApiException::class,
    SteamConnectionException::class,
    AppNewsUnavailableException::class,
    AppNotFoundException::class,
    AppVersionUnavailableException::class,
    InvalidApiKeyException::class,
    InvalidServerAddressException::class,
    ProfileNotPublicException::class,
    StatsUnavailableException::class,
]);

function messageOfFailed(Request $request, MockResponse|Fixture $response): string
{
    $connector = new SteamConnector(new SteamConfig('any'));
    $connector->withMockClient(new MockClient([$response]));

    try {
        $connector->send($request)->dto();
    } catch (SteamApiException $steamApiException) {
        return $steamApiException->getMessage();
    }

    throw new RuntimeException('Expected the request to fail.');
}

test('every failure Steam answered opens with the method', function (Request $request, MockResponse|Fixture $response, string $message): void {
    expect(messageOfFailed($request, $response))->toBe($message);
})->with([
    'an unmapped status' => [
        friendListRequest(),
        MockResponse::make('<html>Server Error</html>', 500),
        'GetFriendList: Steam API request failed with HTTP 500.',
    ],
    'a refusal Steam explains' => [
        new GetServersAtAddressRequest('127.0.0.1'),
        MockResponse::fixture('ISteamApps/GetServersAtAddress/refused'),
        "GetServersAtAddress: Steam API request failed: Please don't call this API more often than once per minute for a given IP.",
    ],
    'a missing key' => [
        friendListRequest(),
        MockResponse::fixture('Errors/missing-key'),
        'GetFriendList: Steam received no API key. Check the key passed to SteamConfig.',
    ],
    'a rejected key' => [
        friendListRequest(),
        MockResponse::fixture('Errors/invalid-key'),
        'GetFriendList: Steam rejected the API key. Check that it is valid and active.',
    ],
    'a profile the connector cannot name' => [
        friendListRequest(),
        MockResponse::make([], 401),
        'GetFriendList: Steam profile is not public.',
    ],
    'a hidden profile' => [
        new GetSteamLevelRequest(SteamId::fromSteamId64('76561198148125221')),
        MockResponse::fixture('IPlayerService/GetSteamLevel/private'),
        'GetSteamLevel: Steam profile 76561198148125221 is not public.',
    ],
    'a hidden or missing profile' => [
        new GetRecentlyPlayedGamesRequest(SteamId::fromSteamId64('76561198148125221')),
        MockResponse::fixture('IPlayerService/GetRecentlyPlayedGames/private'),
        'GetRecentlyPlayedGames: Steam returned no data for profile 76561198148125221: it is not public, or it does not exist.',
    ],
    'stats Steam withholds' => [
        new GetPlayerAchievementsRequest(SteamId::fromSteamId64('76561198148125221'), 440),
        MockResponse::fixture('ISteamUserStats/GetPlayerAchievements/stats-unavailable'),
        'GetPlayerAchievements: Steam returned no stats for app 440: it exposes none, or the profile is private.',
    ],
    'global achievements Steam withholds' => [
        new GetGlobalAchievementPercentagesForAppRequest(440),
        MockResponse::fixture('ISteamUserStats/GetGlobalAchievementPercentagesForApp/no-achievements'),
        'GetGlobalAchievementPercentagesForApp: Steam returned no global achievements for game 440: it carries none, or no game has that ID.',
    ],
    'an unknown app on GetSDRConfig' => [
        new GetSdrConfigRequest(999999999),
        MockResponse::fixture('ISteamApps/GetSDRConfig/unknown-app'),
        'GetSDRConfig: No Steam app found for app ID 999999999.',
    ],
    'an unknown app on GetSchemaForGame' => [
        new GetSchemaForGameRequest(999999999),
        MockResponse::fixture('ISteamUserStats/GetSchemaForGame/unknown-app'),
        'GetSchemaForGame: No Steam app found for app ID 999999999.',
    ],
    'an app Steam cannot check' => [
        new UpToDateCheckRequest(620, 1),
        MockResponse::fixture('ISteamApps/UpToDateCheck/unavailable'),
        'UpToDateCheck: Steam cannot check app 620 for updates: no app has that ID, or it publishes no server version.',
    ],
    'news Steam withholds' => [
        new GetNewsForAppRequest(480),
        MockResponse::fixture('ISteamNews/GetNewsForApp/unavailable'),
        'GetNewsForApp: Steam returned no news for app 480: no app has that ID, or Steam does not publish its news.',
    ],
    'an address Steam rejects' => [
        new GetServersAtAddressRequest('not-an-ip'),
        MockResponse::fixture('ISteamApps/GetServersAtAddress/invalid-address'),
        'GetServersAtAddress: Steam rejected "not-an-ip" as a server address: it takes an IPv4 address, optionally with a query port.',
    ],
]);

test('a connection failure opens with the method', function (Closure $call): void {
    try {
        $call();
    } catch (SteamConnectionException $steamConnectionException) {
        expect($steamConnectionException->getMessage())
            ->toBe('GetFriendList: Could not reach the Steam Web API: cURL error 28: Operation timed out after 5000 milliseconds');

        return;
    }

    throw new RuntimeException('Expected the request to fail.');
})->with([
    'send' => [static fn (): mixed => connectorAnswering([connectionFailure()])->send(friendListRequest())],
    'sendAsync' => [static fn (): mixed => connectorAnswering([connectionFailure()])->sendAsync(friendListRequest())->wait()],
]);

test('a path with no method segment leaves the message bare', function (): void {
    $request = new class extends Request
    {
        protected Method $method = Method::GET;

        public function resolveEndpoint(): string
        {
            return '/';
        }
    };

    expect(messageOfFailed($request, MockResponse::make('', 500)))->toBe('Steam API request failed with HTTP 500.');
});
