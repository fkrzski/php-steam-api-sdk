<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\RateLimiter;
use Fkrzski\SteamApiSdk\SteamConfig;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([RateLimiter::class]);

test("the key's budget is 100 000 requests a day under a hash of the key", function (): void {
    $limiter = new RateLimiter(new SteamConfig, 'test-key');
    $names = limitNames($limiter);

    expect($names)->toBe([
        'SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:100000_every_utc_midnight',
        'SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:too_many_attempts_limit',
    ])
        ->and($limiter->getLimits()[0]->getReleaseInSeconds())->toBe(86_400)
        ->and(implode('', $names))->not->toContain('test-key');
});

test('the anonymous budget holds only the lock after a 429, whatever key the config carries', function (): void {
    expect(limitNames(new RateLimiter(new SteamConfig('test-key'), null)))->toBe(['SteamConnector:anonymous:too_many_attempts_limit']);
});

test('the daily budget resets at the next midnight UTC whatever date.timezone says', function (string $timezone): void {
    $default = date_default_timezone_get();
    date_default_timezone_set($timezone);

    try {
        $expiry = new RateLimiter(new SteamConfig, 'test-key')->getLimits()[0]->getExpiryTimestamp();
    } finally {
        date_default_timezone_set($default);
    }

    expect($expiry % 86_400)->toBe(0)
        ->and($expiry - time())->toBeGreaterThan(0)->toBeLessThanOrEqual(86_400);
})->with(['UTC', 'Pacific/Kiritimati', 'Pacific/Pago_Pago']);

test('the daily budget still resets at midnight UTC when a second passes after the limit is resolved', function (): void {
    $limit = new RateLimiter(new SteamConfig, 'test-key')->getLimits()[0];

    time_sleep_until(time() + 1);

    expect($limit->getExpiryTimestamp() % 86_400)->toBe(0);
});

test('configured limits replace the daily budget in either budget', function (?string $key, string $prefix): void {
    $limiter = new RateLimiter(new SteamConfig('test-key', rateLimits: [Limit::allow(10)->everyMinute()]), $key);

    expect(limitNames($limiter))->toBe([$prefix.':10_every_60', $prefix.':too_many_attempts_limit']);
})->with([
    "the key's" => ['test-key', 'SteamConnector:'.hash('sha256', 'test-key')],
    'the anonymous' => [null, 'SteamConnector:anonymous'],
]);

test('an empty list of limits meters nothing but the lock', function (): void {
    $connector = limitedBy(new SteamConfig(rateLimits: []), 'test-key', MockResponse::make([]), MockResponse::make([]));

    $connector->send(friendListRequest());
    $connector->send(friendListRequest());

    expect(array_keys((new MemoryStore)->getStore()))->toBe(['SteamConnector:'.hash('sha256', 'test-key').':too_many_attempts_limit']);
});

test('the budget lives in the configured store, or in a MemoryStore without one', function (?string $key): void {
    $store = new MemoryStore;

    expect(new RateLimiter(new SteamConfig('test-key', $store), $key)->rateLimitStore())->toBe($store)
        ->and(new RateLimiter(new SteamConfig('test-key'), $key)->rateLimitStore())->toBeInstanceOf(MemoryStore::class);
})->with(['the key' => 'test-key', 'anonymous' => null]);

test('the lock after a 429 lasts the cool-down whatever the budget', function (SteamConfig $config, ?string $key): void {
    $lock = array_last(new RateLimiter($config, $key)->getLimits());

    expect($lock->getName())->toEndWith(':too_many_attempts_limit')
        ->and($lock->getReleaseInSeconds())->toBe(300);
})->with([
    'anonymous' => [new SteamConfig('test-key', tooManyRequestsCooldown: 300), null],
    "the key's" => [new SteamConfig('test-key', tooManyRequestsCooldown: 300), 'test-key'],
    'no local limits' => [new SteamConfig('test-key', rateLimits: [], tooManyRequestsCooldown: 300), 'test-key'],
    'own limits' => [new SteamConfig('test-key', rateLimits: [Limit::allow(10)->everyMinute()], tooManyRequestsCooldown: 300), 'test-key'],
]);

test('the plugin names its lock after a 429 as the limiter reserves it', function (): void {
    expect(array_last(new RateLimiter(new SteamConfig, null)->getLimits())->getName())
        ->toBe('SteamConnector:anonymous:'.RateLimiter::TOO_MANY_ATTEMPTS_LIMIT);
});

test('each key keeps a counter of its own', function (): void {
    $config = new SteamConfig(rateLimits: [Limit::allow(1)->everyMinute()]);

    limitedBy($config, 'key-aaa', MockResponse::make([]))->send(friendListRequest());

    expect(fn (): Response => limitedBy($config, 'key-aaa', MockResponse::make([]))->send(friendListRequest()))->toThrow(SteamRateLimitException::class)
        ->and(limitedBy($config, 'key-bbb', MockResponse::make([]))->send(friendListRequest())->status())->toBe(200);
});

test('a spent budget is refused until midnight UTC', function (): void {
    $limiter = new RateLimiter(new SteamConfig, 'test-key');
    $limiter->getLimits()[0]->hit(100_000)->save($limiter->rateLimitStore());

    $refused = rateLimitOf(fn (): Response => limitedBy(new SteamConfig, 'test-key', MockResponse::make([]))->send(friendListRequest()));

    expect($refused->limit->getExpiryTimestamp() % 86_400)->toBe(0)
        ->and($refused->limit->getRemainingSeconds())->toBeGreaterThan(0)->toBeLessThanOrEqual(86_400);
});

test('a spent configured limit is refused with that limit', function (): void {
    $connector = limitedBy(new SteamConfig(rateLimits: [Limit::allow(3)->everyMinute()]), 'test-key', ...array_fill(0, 4, MockResponse::make([])));

    $connector->send(friendListRequest());
    $connector->send(friendListRequest());
    $connector->send(friendListRequest());

    $refused = rateLimitOf(fn (): Response => $connector->send(friendListRequest()));

    expect($refused->limit->getAllow())->toBe(3)
        ->and($refused->getMessage())->toBe(sprintf('Steam API rate limit reached (limit: %s).', $refused->limit->getName()));
});

test("Steam's 429 raises an exception carrying its answer, and the lock refuses with none", function (?string $key): void {
    $connector = limitedBy(new SteamConfig, $key, MockResponse::make([], 429, ['Retry-After' => '120']), MockResponse::make([]));

    $throttled = rateLimitOf(fn (): Response => $connector->send(friendListRequest()));
    $refused = rateLimitOf(fn (): Response => $connector->send(friendListRequest()));

    expect($throttled->getCode())->toBe(429)
        ->and($throttled->response?->header('Retry-After'))->toBe('120')
        ->and($refused->response)->toBeNull()
        ->and($refused->getCode())->toBe(0)
        ->and($refused->limit->getName())->toBe($throttled->limit->getName());
})->with(['the key' => 'test-key', 'anonymous' => null]);

test('a 429 locks for as long as Retry-After says, or for the cool-down without one', function (SteamConfig $config, array $headers, int $seconds): void {
    $before = time();
    $throttled = rateLimitOf(fn (): Response => limitedBy($config, 'test-key', MockResponse::make([], 429, $headers))->send(friendListRequest()));
    $after = time();

    expect($throttled->limit->getExpiryTimestamp())->toBeGreaterThanOrEqual($before + $seconds)->toBeLessThanOrEqual($after + $seconds);
})->with([
    'default cool-down' => [new SteamConfig, [], 60],
    'Retry-After under the cool-down' => [new SteamConfig(tooManyRequestsCooldown: 300), ['Retry-After' => '120'], 120],
    'Retry-After over the cool-down' => [new SteamConfig(tooManyRequestsCooldown: 30), ['Retry-After' => '120'], 120],
]);

test('a Retry-After of 0 or in the past locks for one second, not the cool-down', function (Closure $retryAfter): void {
    $before = time();
    $throttled = rateLimitOf(fn (): Response => limitedBy(
        new SteamConfig(tooManyRequestsCooldown: 600),
        'test-key',
        MockResponse::make([], 429, ['Retry-After' => $retryAfter()]),
    )->send(friendListRequest()));
    $after = time();

    expect($throttled->limit->getExpiryTimestamp())->toBeGreaterThanOrEqual($before + 1)->toBeLessThanOrEqual($after + 1);
})->with([
    'zero' => [static fn (): string => '0'],
    'negative' => [static fn (): string => '-5'],
    'a date in the past' => [static fn (): string => gmdate('D, d M Y H:i:s \G\M\T', time() - 30)],
]);

test('a 429 without Retry-After late in the cool-down window locks for the whole cool-down', function (): void {
    $config = new SteamConfig(tooManyRequestsCooldown: 300);
    $limiter = new RateLimiter($config, 'test-key');

    array_last($limiter->getLimits())->setExpiryTimestamp(time() + 5)->save($limiter->rateLimitStore());

    $before = time();
    $throttled = rateLimitOf(fn (): Response => limitedBy($config, 'test-key', MockResponse::make([], 429))->send(friendListRequest()));
    $after = time();

    expect($throttled->limit->getExpiryTimestamp())->toBeGreaterThanOrEqual($before + 300)->toBeLessThanOrEqual($after + 300);
});

/**
 * @return list<string>
 */
function limitNames(RateLimiter $limiter): array
{
    return array_map(static fn (Limit $limit): string => $limit->getName(), $limiter->getLimits());
}

/**
 * A bare Saloon connector, so nothing but the limiter stands between a request and its answer.
 */
function limitedBy(SteamConfig $config, ?string $key, MockResponse ...$answers): Connector
{
    return new class($config, $key) extends Connector
    {
        public function __construct(private readonly SteamConfig $steamConfig, private readonly ?string $apiKey) {}

        public function resolveBaseUrl(): string
        {
            return 'https://api.steampowered.com';
        }

        public function boot(PendingRequest $pendingRequest): void
        {
            new RateLimiter($this->steamConfig, $this->apiKey)->boot($pendingRequest);
        }
    }->withMockClient(new MockClient(array_values($answers)));
}
