<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([SteamRateLimitException::class, SteamConnector::class, ApiKeyNotConfiguredException::class]);

beforeEach(function (): void {
    MemoryStore::clear();
});

test('connector exposes Steam daily limit of 100k requests', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));

    $limits = $connector->getLimits();
    $primary = $limits[0];

    expect($primary)->toBeInstanceOf(Limit::class)
        ->and($primary->getAllow())->toBe(100_000)
        ->and($primary->getName())->toContain('SteamConnector')
        ->and($primary->getName())->toContain('100000');
});

test('connector defaults to a MemoryStore when none is configured', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));

    expect($connector->rateLimitStore())->toBeInstanceOf(MemoryStore::class);
});

test('connector honours the configured rate limit store', function (): void {
    $store = new MemoryStore;
    $connector = new SteamConnector(new SteamConfig('test-key', $store));

    expect($connector->rateLimitStore())->toBe($store);
});

test('limits are keyed by a hash of the API key', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));

    $names = array_map(
        static fn (Limit $limit): string => $limit->getName(),
        $connector->getLimits(),
    );

    expect($names)->toBe([
        'SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:100000_every_86400',
        'SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:too_many_attempts_limit',
    ])->and(implode('', $names))->not->toContain('test-key');
});

test('each API key gets its own counter', function (): void {
    $first = sendVanityUrlRequest('key-aaa');
    $second = sendVanityUrlRequest('key-bbb');

    expect(dailyLimitKeys())->toHaveCount(2)
        ->and(dailyHits($first))->toBe(1)
        ->and(dailyHits($second))->toBe(1);
});

test('connectors sharing an API key share one counter', function (): void {
    sendVanityUrlRequest('key-aaa');
    $second = sendVanityUrlRequest('key-aaa');

    expect(dailyLimitKeys())->toHaveCount(1)
        ->and(dailyHits($second))->toBe(2);
});

test('without an API key the daily budget is not metered at all', function (): void {
    $connector = new SteamConnector(new SteamConfig);

    $names = array_map(
        static fn (Limit $limit): string => $limit->getName(),
        $connector->getLimits(),
    );

    expect($names)->toBe(['SteamConnector:anonymous:too_many_attempts_limit']);
});

test('anonymous requests leave no daily counter behind', function (): void {
    sendCurrentPlayersRequest();
    sendCurrentPlayersRequest();

    expect(dailyLimitKeys())->toBeEmpty();
});

test('Steam throttling anonymous traffic still raises SteamRateLimitException', function (): void {
    $connector = new SteamConnector(new SteamConfig);

    $connector->withMockClient(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
    ]));

    expect(fn (): mixed => $connector->send(new GetNumberOfCurrentPlayersRequest(381210)))
        ->toThrow(SteamRateLimitException::class);
});

test('a request refused for a missing API key never reaches the wire', function (): void {
    $connector = new SteamConnector(new SteamConfig);

    $connector->withMockClient($mockClient = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]));

    expect(fn (): mixed => $connector->send(new ResolveVanityUrlRequest('nick')))
        ->toThrow(ApiKeyNotConfiguredException::class)
        ->and($mockClient->getRecordedResponses())->toBeEmpty();
});

test('hitting the limit throws SteamRateLimitException with the offending limit', function (): void {
    $connector = new class(new SteamConfig('test-key')) extends SteamConnector
    {
        protected function resolveLimits(): array
        {
            return [Limit::allow(3)->everyMinute()];
        }
    };

    $mock = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]);

    $connector->withMockClient($mock);

    $connector->send(new ResolveVanityUrlRequest('first'));
    $connector->send(new ResolveVanityUrlRequest('second'));
    $connector->send(new ResolveVanityUrlRequest('third'));

    try {
        $connector->send(new ResolveVanityUrlRequest('fourth'));
        $this->fail('Expected SteamRateLimitException was not thrown.');
    } catch (SteamRateLimitException $steamRateLimitException) {
        expect($steamRateLimitException->limit)->toBeInstanceOf(Limit::class)
            ->and($steamRateLimitException->limit->getAllow())->toBe(3)
            ->and($steamRateLimitException->getMessage())->toContain('Steam API rate limit reached');
    }
});

function sendVanityUrlRequest(string $apiKey): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig($apiKey));

    $connector->withMockClient(new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]));

    $connector->send(new ResolveVanityUrlRequest('nick'));

    return $connector;
}

function sendCurrentPlayersRequest(): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig);

    $connector->withMockClient(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture('ISteamUserStats/GetNumberOfCurrentPlayers/default'),
    ]));

    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));

    return $connector;
}

/**
 * @return list<string>
 */
function dailyLimitKeys(): array
{
    return array_values(array_filter(
        array_keys((new MemoryStore)->getStore()),
        static fn (string $key): bool => str_ends_with($key, '100000_every_86400'),
    ));
}

function dailyHits(SteamConnector $connector): int
{
    return $connector->getLimits()[0]->update($connector->rateLimitStore())->getHits();
}
