<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([
    GetBadgesRequest::class,
    GetCommunityBadgeProgressRequest::class,
    GetOwnedGamesRequest::class,
    GetRecentlyPlayedGamesRequest::class,
    GetSteamLevelRequest::class,
    GetServersAtAddressRequest::class,
    UpToDateCheckRequest::class,
    ResolveVanityUrlRequest::class,
]);

beforeEach(function (): void {
    MemoryStore::clear();
});

function payloadFailureConnector(MockClient $mockClient, int $tries = 1): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig('any', tries: $tries));
    $connector->withMockClient($mockClient);

    return $connector;
}

function payloadFailureOf(Closure $call): SteamApiException
{
    try {
        $call();
    } catch (SteamApiException $steamApiException) {
        return $steamApiException;
    }

    throw new RuntimeException('Expected the call to fail.');
}

dataset('failures in a 200 payload', [
    'GetOwnedGames' => [
        new GetOwnedGamesRequest(SteamId::fromSteamId64('76561198148125221')),
        'IPlayerService/GetOwnedGames/private',
        ProfileNotPublicException::class,
    ],
    'GetSteamLevel' => [
        new GetSteamLevelRequest(SteamId::fromSteamId64('76561198148125221')),
        'IPlayerService/GetSteamLevel/private',
        ProfileNotPublicException::class,
    ],
    'GetBadges' => [
        new GetBadgesRequest(SteamId::fromSteamId64('76561198148125221')),
        'IPlayerService/GetBadges/private',
        ProfileNotPublicException::class,
    ],
    'GetRecentlyPlayedGames' => [
        new GetRecentlyPlayedGamesRequest(SteamId::fromSteamId64('76561198148125221')),
        'IPlayerService/GetRecentlyPlayedGames/private',
        ProfileNotPublicException::class,
    ],
    'GetCommunityBadgeProgress' => [
        new GetCommunityBadgeProgressRequest(SteamId::fromSteamId64('76561198148125221')),
        'IPlayerService/GetCommunityBadgeProgress/private',
        ProfileNotPublicException::class,
    ],
    'ResolveVanityURL' => [
        new ResolveVanityUrlRequest('missingUser'),
        'ISteamUser/ResolveVanityUrl/not_found',
        SteamUserNotFoundException::class,
    ],
    'UpToDateCheck' => [
        new UpToDateCheckRequest(620, 1),
        'ISteamApps/UpToDateCheck/unavailable',
        AppVersionUnavailableException::class,
    ],
    'GetServersAtAddress, a rejected address' => [
        new GetServersAtAddressRequest('not-an-ip'),
        'ISteamApps/GetServersAtAddress/invalid-address',
        InvalidServerAddressException::class,
    ],
    'GetServersAtAddress, a refused lookup' => [
        new GetServersAtAddressRequest('127.0.0.1'),
        'ISteamApps/GetServersAtAddress/refused',
        SteamApiException::class,
    ],
]);

test('a failure in a 200 payload fails the call itself', function (Request $request, string $fixture, string $class, Closure $call): void {
    $mockClient = new MockClient([MockResponse::fixture($fixture)]);

    $exception = payloadFailureOf(static fn (): mixed => $call(payloadFailureConnector($mockClient), $request));

    expect($exception::class)->toBe($class)
        ->and($exception->response)->toBe($mockClient->getLastResponse())
        ->and($exception->getCode())->toBe(200);
})->with('failures in a 200 payload')->with([
    'send' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->send($request)],
    'sendAsync' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->sendAsync($request)->wait()],
]);

test('a failure in a 200 payload is not retried', function (Request $request, string $fixture): void {
    $mockClient = new MockClient(array_fill(0, 3, MockResponse::fixture($fixture)));

    payloadFailureOf(static fn (): Response => payloadFailureConnector($mockClient, tries: 3)->send($request));

    $mockClient->assertSentCount(1);
})->with('failures in a 200 payload');

test('pool hands a failure in a 200 payload to the exception handler', function (Request $request, string $fixture, string $class): void {
    $handled = [];
    $rejected = [];

    payloadFailureConnector(new MockClient([MockResponse::fixture($fixture)]))->pool(
        [$request],
        responseHandler: static function (Response $response) use (&$handled): void {
            $handled[] = $response;
        },
        exceptionHandler: static function (Throwable $throwable) use (&$rejected): void {
            $rejected[] = $throwable::class;
        },
    )->send()->wait();

    expect($handled)->toBeEmpty()
        ->and($rejected)->toBe([$class]);
})->with('failures in a 200 payload');

test('any other status is left to the connector', function (Request $request, string $method, MockResponse $answer, int $status): void {
    $exception = payloadFailureOf(static fn (): Response => payloadFailureConnector(new MockClient([$answer]))->send($request));

    expect($exception::class)->toBe(SteamApiException::class)
        ->and($exception->getMessage())->toBe(sprintf('%s: Steam API request failed with HTTP %d.', $method, $status));
})->with([
    'GetOwnedGames' => [new GetOwnedGamesRequest(SteamId::fromSteamId64('76561198148125221')), 'GetOwnedGames'],
    'GetSteamLevel' => [new GetSteamLevelRequest(SteamId::fromSteamId64('76561198148125221')), 'GetSteamLevel'],
    'GetBadges' => [new GetBadgesRequest(SteamId::fromSteamId64('76561198148125221')), 'GetBadges'],
    'GetRecentlyPlayedGames' => [new GetRecentlyPlayedGamesRequest(SteamId::fromSteamId64('76561198148125221')), 'GetRecentlyPlayedGames'],
    'GetCommunityBadgeProgress' => [new GetCommunityBadgeProgressRequest(SteamId::fromSteamId64('76561198148125221')), 'GetCommunityBadgeProgress'],
    'ResolveVanityURL' => [new ResolveVanityUrlRequest('missingUser'), 'ResolveVanityURL'],
    'UpToDateCheck' => [new UpToDateCheckRequest(620, 1), 'UpToDateCheck'],
    'GetServersAtAddress' => [new GetServersAtAddressRequest('127.0.0.1'), 'GetServersAtAddress'],
])->with([
    'a JSON 4xx' => [MockResponse::make([], 400), 400],
    'an HTML 5xx' => [MockResponse::make('<html>Server Error</html>', 500), 500],
]);
