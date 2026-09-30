<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Saloon\Exceptions\Request\Statuses\ServiceUnavailableException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([SteamConnector::class, SteamApiException::class]);

beforeEach(function (): void {
    MemoryStore::clear();
});

afterEach(function (): void {
    MemoryStore::clear();
});

function retrying(int $tries = 3): SteamConfig
{
    return new SteamConfig('any', tries: $tries);
}

function friendList(): PsrResponse
{
    return new PsrResponse(200, ['Content-Type' => 'application/json'], '{"friendslist":{"friends":[]}}');
}

test('a 5xx is retried until Steam answers', function (): void {
    $connector = connectorAnswering([new PsrResponse(503), new PsrResponse(503), friendList()], retrying());

    expect($connector->send(friendListRequest())->status())->toBe(200);
});

test('a 5xx on the last try raises the failure it answered with', function (): void {
    $connector = connectorAnswering([new PsrResponse(503), new PsrResponse(503), new PsrResponse(503)], retrying());

    try {
        $connector->send(friendListRequest());
    } catch (SteamApiException $steamApiException) {
        expect($steamApiException->getMessage())->toBe('Steam API request failed with HTTP 503.')
            ->and($steamApiException->getCode())->toBe(503)
            ->and($steamApiException->getPrevious())->toBeInstanceOf(ServiceUnavailableException::class);

        return;
    }

    throw new RuntimeException('Expected the request to fail.');
});

test('a connection failure is retried until Steam answers', function (): void {
    $connector = connectorAnswering([connectionFailure(), connectionFailure(), friendList()], retrying());

    expect($connector->send(friendListRequest())->status())->toBe(200);
});

test('a 4xx is never retried', function (): void {
    $connector = connectorAnswering([new PsrResponse(404), friendList()], retrying());

    expect(fn (): mixed => $connector->send(friendListRequest()))
        ->toThrow(SteamApiException::class, 'Steam API request failed with HTTP 404.');
});

test('a failure the SDK maps itself is never retried', function (): void {
    $connector = connectorAnswering([new PsrResponse(403, [], '{"playerstats":{"success":false}}'), friendList()], retrying());

    expect(fn (): mixed => $connector->send(friendListRequest()))
        ->toThrow(ProfileNotPublicException::class);
});

test('a 429 is never retried', function (): void {
    $connector = new SteamConnector(retrying());

    $connector->withMockClient($mockClient = new MockClient([
        GetFriendListRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
    ]));

    expect(fn (): mixed => $connector->send(friendListRequest()))
        ->toThrow(SteamRateLimitException::class);

    $mockClient->assertSentCount(1);
});

test('the default config sends every request once', function (): void {
    $connector = connectorAnswering([new PsrResponse(503), friendList()]);

    expect(fn (): mixed => $connector->send(friendListRequest()))
        ->toThrow(SteamApiException::class, 'Steam API request failed with HTTP 503.');
});

test('sendAsync sends every request once', function (): void {
    $connector = connectorAnswering([new PsrResponse(503), friendList()], retrying());

    expect(fn (): mixed => $connector->sendAsync(friendListRequest())->wait())
        ->toThrow(SteamApiException::class, 'Steam API request failed with HTTP 503.');
});

test('the connector hands the pause and the backoff to Saloon', function (): void {
    $connector = new SteamConnector(new SteamConfig('any', tries: 3, retryInterval: 250, exponentialBackoff: true));

    expect($connector->tries)->toBe(3)
        ->and($connector->retryInterval)->toBe(250)
        ->and($connector->useExponentialBackoff)->toBeTrue();
});
