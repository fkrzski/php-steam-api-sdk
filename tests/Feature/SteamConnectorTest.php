<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

covers([SteamConnector::class, InvalidApiKeyException::class, ProfileNotPublicException::class]);

test('base URL resolves to Steam Web API host', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    expect($connector->resolveBaseUrl())->toBe('https://api.steampowered.com');
});

test('default query carries api key', function (): void {
    $connector = new SteamConnector(new SteamConfig('secret-key'));

    expect($connector->query()->all())->toBe(['key' => 'secret-key']);
});

test('resource accessors expose one resource per Steam interface', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    expect($connector->players())->toBeInstanceOf(PlayersResource::class)
        ->and($connector->users())->toBeInstanceOf(UsersResource::class)
        ->and($connector->stats())->toBeInstanceOf(StatsResource::class);
});

test('connector uses AlwaysThrowOnErrors plugin', function (): void {
    expect(in_array(AlwaysThrowOnErrors::class, class_uses(SteamConnector::class), true))->toBeTrue();
});

test('resolveLimits exposes a single 100,000 requests-per-day limit', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    /** @var array<Limit> $limits */
    $limits = $connector->getLimits();

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->getAllow())->toBe(100_000);
});

test('resolveRateLimitStore returns the store configured on SteamConfig', function (): void {
    $store = new MemoryStore;
    $connector = new SteamConnector(new SteamConfig('any', $store));

    $rateLimitStore = $connector->rateLimitStore();

    expect($rateLimitStore)->toBe($store);
});

test('resolveRateLimitStore falls back to a fresh in-memory store', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    $rateLimitStore = $connector->rateLimitStore();

    expect($rateLimitStore)->toBeInstanceOf(MemoryStore::class);
});

/**
 * @return array<string, mixed>
 */
function bootedQuery(?Language $configured, Request $request): array
{
    $connector = new SteamConnector(new SteamConfig('any', language: $configured));

    return $connector->createPendingRequest($request)->query()->all();
}

function achievementsRequest(?Language $language = null): GetPlayerAchievementsRequest
{
    return new GetPlayerAchievementsRequest(SteamId::fromSteamId64('76561198148125221'), 381210, $language);
}

test('the configured language fills in the l parameter a request left open', function (): void {
    expect(bootedQuery(Language::Polish, achievementsRequest()))->toHaveKey('l', 'polish');
});

test('a language passed to the request wins over the configured one', function (): void {
    expect(bootedQuery(Language::Polish, achievementsRequest(Language::English)))->toHaveKey('l', 'english');
});

test('endpoints that do not take a language never receive one', function (): void {
    $query = bootedQuery(Language::Polish, new GetFriendListRequest(SteamId::fromSteamId64('76561198148125221')));

    expect($query)->not->toHaveKey('l');
});

test('without a configured language the parameter stays off', function (): void {
    expect(bootedQuery(null, achievementsRequest()))->not->toHaveKey('l');
});

/**
 * Send through the connector so the failure travels the real middleware path.
 */
function sendFailing(MockResponse|Fixture $response): Throwable
{
    $connector = new SteamConnector(new SteamConfig('any'));
    $connector->withMockClient(new MockClient([GetFriendListRequest::class => $response]));

    try {
        $connector->send(new GetFriendListRequest(SteamId::fromSteamId64('76561198148125221')));
    } catch (Throwable $throwable) {
        return $throwable;
    }

    throw new RuntimeException('Expected the request to fail.');
}

test('401 maps to ProfileNotPublicException', function (): void {
    expect(sendFailing(MockResponse::make([], 401)))
        ->toBeInstanceOf(ProfileNotPublicException::class)
        ->and(sendFailing(MockResponse::make([], 401))->getMessage())->toBe('Steam profile is not public.');
});

test('403 carrying a JSON body maps to ProfileNotPublicException', function (): void {
    expect(sendFailing(MockResponse::make(['playerstats' => ['success' => false]], 403)))
        ->toBeInstanceOf(ProfileNotPublicException::class);
});

test('403 naming the key parameter maps to InvalidApiKeyException', function (): void {
    $thrown = sendFailing(MockResponse::fixture('Errors/invalid-key'));

    expect($thrown)->toBeInstanceOf(InvalidApiKeyException::class)
        ->and($thrown->getMessage())->toBe('Steam rejected the API key. Check that it is valid and active.');
});

test('401 naming the key parameter maps to InvalidApiKeyException', function (): void {
    $thrown = sendFailing(MockResponse::fixture('Errors/unauthorized-key'));

    expect($thrown)->toBeInstanceOf(InvalidApiKeyException::class)
        ->and($thrown->getMessage())->toBe('Steam rejected the API key. Check that it is valid and active.');
});

test('400 reporting a missing key maps to InvalidApiKeyException', function (): void {
    $thrown = sendFailing(MockResponse::fixture('Errors/missing-key'));

    expect($thrown)->toBeInstanceOf(InvalidApiKeyException::class)
        ->and($thrown->getMessage())->toBe('Steam API key is missing. Check the key passed to SteamConfig.');
});

test('400 unrelated to the key falls back to SteamApiException', function (): void {
    $thrown = sendFailing(MockResponse::make(['error' => 'whatever'], 400));

    expect($thrown)->toBeInstanceOf(SteamApiException::class)
        ->and($thrown->getMessage())->toBe('Steam API request failed with HTTP 400.');
});

test('server errors fall back to SteamApiException', function (): void {
    $thrown = sendFailing(MockResponse::make('<html>Server Error</html>', 500));

    expect($thrown)->toBeInstanceOf(SteamApiException::class)
        ->and($thrown->getMessage())->toBe('Steam API request failed with HTTP 500.');
});

test('mapped exceptions carry the status code and the originating response', function (): void {
    /** @var SteamApiException $thrown */
    $thrown = sendFailing(MockResponse::make([], 401));

    expect($thrown->getCode())->toBe(401)
        ->and($thrown->response)->not->toBeNull()
        ->and($thrown->response?->status())->toBe(401);
});

test('every mapped failure stays inside the SDK exception hierarchy', function (): void {
    expect(sendFailing(MockResponse::make([], 401)))->toBeInstanceOf(SteamApiException::class)
        ->and(sendFailing(MockResponse::fixture('Errors/invalid-key')))->toBeInstanceOf(SteamApiException::class)
        ->and(sendFailing(MockResponse::fixture('Errors/missing-key')))->toBeInstanceOf(SteamApiException::class)
        ->and(sendFailing(MockResponse::make('', 503)))->toBeInstanceOf(SteamApiException::class);
});
