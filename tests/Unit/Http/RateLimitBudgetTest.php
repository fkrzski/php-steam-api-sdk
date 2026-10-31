<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Allowance;
use Fkrzski\SteamApiSdk\Http\RateLimitBudget;
use Fkrzski\SteamApiSdk\Http\RateLimiter;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\UtcDayLimit;
use Fkrzski\SteamApiSdk\SteamConfig;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Limit;

covers([RateLimitBudget::class, Allowance::class, RateLimiter::class]);

test("the budget reports what is left of the key's day", function (): void {
    $limiter = new RateLimiter(new SteamConfig, 'test-key');
    $limiter->getLimits()[0]->hit(40)->save($limiter->rateLimitStore());

    expect(new RateLimitBudget($limiter)->remaining())->toEqual([
        new Allowance(allow: 100_000, left: 99_960, window: null, sleeps: false),
    ]);
});

test('the budget reports each limit of its own under its key, in the order they are checked', function (): void {
    $limiter = new RateLimiter(new SteamConfig(rateLimits: [
        'burst' => Limit::allow(5)->everySeconds(1)->sleep(),
        'hour' => Limit::allow(1_000)->everyHour(),
        'day' => UtcDayLimit::requests(500_000),
    ]), 'test-key');
    $limiter->getLimits()['burst']->hit(2)->save($limiter->rateLimitStore());

    $remaining = new RateLimitBudget($limiter)->remaining();

    expect(array_keys($remaining))->toBe(['burst', 'hour', 'day'])
        ->and($remaining)->toEqual([
            'burst' => new Allowance(allow: 5, left: 3, window: 1, sleeps: true),
            'hour' => new Allowance(allow: 1_000, left: 1_000, window: 3_600, sleeps: false),
            'day' => new Allowance(allow: 500_000, left: 500_000, window: null, sleeps: false),
        ]);
});

test('nothing is left of a limit a pool sent past', function (): void {
    $limiter = new RateLimiter(new SteamConfig(rateLimits: [Limit::allow(5)->everyMinute()]), 'test-key');
    $limiter->getLimits()[0]->hit(7)->save($limiter->rateLimitStore());

    expect(new RateLimitBudget($limiter)->remaining()[0]->left)->toBe(0);
});

test('a window that has closed leaves the whole limit', function (): void {
    $limiter = new RateLimiter(new SteamConfig, 'test-key');
    $limiter->rateLimitStore()->set(
        $limiter->getLimits()[0]->getName(),
        json_encode(['timestamp' => time() - 5, 'hits' => 100_000], JSON_THROW_ON_ERROR),
        60,
    );

    expect(new RateLimitBudget($limiter)->remaining()[0]->left)->toBe(100_000)
        ->and(new RateLimitBudget($limiter)->wait())->toBeNull();
});

test('the lock after a 429 holds the next request back without showing as an allowance', function (): void {
    $connector = fakedConnector(new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
    ]));

    rateLimitOf(fn (): Response => $connector->send(new ResolveVanityUrlRequest('nick')));

    expect(array_keys($connector->rateLimitBudget()->remaining()))->toBe([0])
        ->and($connector->rateLimitBudget()->wait())->toBeGreaterThanOrEqual(119)->toBeLessThanOrEqual(120);
});

test('the anonymous budget counts nothing by default', function (): void {
    expect(new RateLimitBudget(new RateLimiter(new SteamConfig('test-key'), null))->remaining())->toBe([]);
});

test('nothing holds the next request back while the budget has room', function (): void {
    expect(new RateLimitBudget(new RateLimiter(new SteamConfig, 'test-key'))->wait())->toBeNull();
});

test('a spent day holds the next request back until midnight UTC', function (): void {
    $limiter = new RateLimiter(new SteamConfig, 'test-key');
    $limiter->getLimits()[0]->hit(100_000)->save($limiter->rateLimitStore());
    $untilMidnight = new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp() - time();

    expect(new RateLimitBudget($limiter)->wait())->toBeGreaterThanOrEqual($untilMidnight - 1)->toBeLessThanOrEqual($untilMidnight);
});

test('a threshold holds the next request back before the limit is spent', function (): void {
    $limiter = new RateLimiter(new SteamConfig, 'test-key');
    $limiter->getLimits()[0]->hit(90_000)->save($limiter->rateLimitStore());

    expect(new RateLimitBudget($limiter)->wait())->toBeNull()
        ->and(new RateLimitBudget($limiter)->wait(0.9))->toBeGreaterThan(0);
});

test('the wait after a refusal lasts until the limit that refused lets requests through', function (): void {
    $connector = fakedConnector(new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]));
    $connector->getLimits()[0]->hit(100_000)->save($connector->rateLimitStore());
    $untilMidnight = new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp() - time();

    $refusal = rateLimitOf(fn (): Response => $connector->send(new ResolveVanityUrlRequest('nick')));

    expect($connector->rateLimitBudget()->waitAfter($refusal))->toBeGreaterThanOrEqual($untilMidnight - 1)->toBeLessThanOrEqual($untilMidnight);
});

test('a wait lasts at least a second', function (int $expiresIn): void {
    $refusal = SteamRateLimitException::fromLimit(Limit::allow(1)->everyMinute()->setExpiryTimestamp(time() + $expiresIn));

    expect(new RateLimitBudget(new RateLimiter(new SteamConfig, 'test-key'))->waitAfter($refusal))->toBe(1);
})->with(['a window that closed' => -5, 'one closing now' => 0, 'one closing in a second' => 1]);
