<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Allowance;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\UtcDayLimit;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\PlayerBadgesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGamesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\AppVersionCheckFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrConfigFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\AppNewsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GameSchemaFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatsFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\AppsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\NewsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\PlayersFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\StatsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\UsersFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\WebApiFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\RateLimitPlugin\Limit;

covers(SteamFake::class, UsersFake::class, PlayersFake::class, StatsFake::class, AppsFake::class, NewsFake::class, WebApiFake::class);

test('hands the same mock back from every endpoint', function (Closure $fakeEndpoint): void {
    $fake = new SteamFake;

    expect($fakeEndpoint($fake))->toBe($fake);
})->with([
    'summaries' => [fn (SteamFake $fake): SteamFake => $fake->users()->summaries()],
    'bans' => [fn (SteamFake $fake): SteamFake => $fake->users()->bans()],
    'friends' => [fn (SteamFake $fake): SteamFake => $fake->users()->friends()],
    'groups' => [fn (SteamFake $fake): SteamFake => $fake->users()->groups()],
    'resolve vanity url' => [fn (SteamFake $fake): SteamFake => $fake->users()->resolveVanityUrl(steamId())],
    'owned games' => [fn (SteamFake $fake): SteamFake => $fake->players()->ownedGames()],
    'recently played games' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()),
    ],
    'steam level' => [fn (SteamFake $fake): SteamFake => $fake->players()->steamLevel(42)],
    'badges' => [fn (SteamFake $fake): SteamFake => $fake->players()->badges(PlayerBadgesFactory::new())],
    'community badge progress' => [fn (SteamFake $fake): SteamFake => $fake->players()->communityBadgeProgress()],
    'user stats' => [fn (SteamFake $fake): SteamFake => $fake->stats()->userStats(UserStatsFactory::new())],
    'achievements' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->achievements(PlayerAchievementsFactory::new()),
    ],
    'current players' => [fn (SteamFake $fake): SteamFake => $fake->stats()->currentPlayers(12_345)],
    'global achievements' => [fn (SteamFake $fake): SteamFake => $fake->stats()->globalAchievements()],
    'schema' => [fn (SteamFake $fake): SteamFake => $fake->stats()->schema(GameSchemaFactory::new())],
    'up to date check' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->upToDateCheck(AppVersionCheckFactory::new()),
    ],
    'servers at address' => [fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddress()],
    'sdr config' => [fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfig(SdrConfigFactory::new())],
    'app news' => [fn (SteamFake $fake): SteamFake => $fake->news()->appNews(AppNewsFactory::new())],
    'news feed' => [fn (SteamFake $fake): SteamFake => $fake->news()->newsFeed(NewsItemFactory::new())],
    'server info' => [
        fn (SteamFake $fake): SteamFake => $fake->webApi()->serverTime(new DateTimeImmutable('2026-10-08 19:59:38 UTC')),
    ],
    'supported api list' => [fn (SteamFake $fake): SteamFake => $fake->webApi()->supportedApis()],
]);

test('chains from the array form', function (): void {
    $connector = fakedConnector(new SteamFake([
        ResolveVanityUrlRequest::class => MockResponse::make(['response' => ['steamid' => '76561198000000000', 'success' => 1]]),
    ])->players()->steamLevel(42));

    expect($connector->users()->resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000')
        ->and($connector->players()->steamLevel(steamId()))->toBe(42);
});

test('fails every request without its own response', function (Closure $fail, string $exception, ?string $message = null): void {
    $fake = new SteamFake()->players()->steamLevel(42);
    $connector = fakedConnector($fake);

    expect($fail($fake))->toBe($fake)
        ->and($connector->players()->steamLevel(steamId()))->toBe(42)
        ->and(fn (): array => $connector->users()->summaries([steamId()]))->toThrow($exception, $message);
})->with([
    'invalid api key' => [
        fn (SteamFake $fake): SteamFake => $fake->invalidApiKey(),
        InvalidApiKeyException::class,
    ],
    'connection failed' => [
        fn (SteamFake $fake): SteamFake => $fake->connectionFailed(),
        SteamConnectionException::class,
        'Could not reach the Steam Web API: cURL error 28: Operation timed out after 10000 milliseconds',
    ],
    'too many requests' => [
        fn (SteamFake $fake): SteamFake => $fake->tooManyRequests(),
        SteamRateLimitException::class,
    ],
]);

test('rejects a key with the page steam was captured sending', function (): void {
    $answer = answerFrom(new SteamFake()->invalidApiKey(), new GetPlayerSummariesRequest([steamId()]));

    expect([$answer->status(), $answer->body()])->toBe(captured('Errors/invalid-key'));
});

test('retries a connection failure without recording any attempt', function (): void {
    $attempts = 0;
    $fake = new SteamFake()->connectionFailed();
    $connector = fakedConnector($fake, new SteamConfig('test-key', tries: 3))->onRequest(static function () use (&$attempts): void {
        $attempts++;
    });

    expect(fn (): array => $connector->users()->summaries([steamId()]))->toThrow(SteamConnectionException::class)
        ->and($attempts)->toBe(3)
        ->and($fake->getRecordedResponses())->toBeEmpty();
});

test('records no connection failure however it was sent', function (Closure $send): void {
    $fake = new SteamFake()->connectionFailed();

    expect(failureFrom(fn (): mixed => $send(fakedConnector($fake))))->toBeInstanceOf(SteamConnectionException::class)
        ->and($fake->getRecordedResponses())->toBeEmpty();
})->with(everyWayToSend());

test('throttles with a 429 that carries Retry-After only when given one', function (?int $retryAfter, ?string $header): void {
    $answer = answerFrom(new SteamFake()->tooManyRequests($retryAfter), new GetPlayerSummariesRequest([steamId()]));

    expect($answer->status())->toBe(429)
        ->and($answer->body())->toBe('[]')
        ->and($answer->header('Retry-After'))->toBe($header);
})->with([
    'without Retry-After' => [null, null],
    'with Retry-After' => [120, '120'],
]);

test('refuses requests locally for the cool-down or Retry-After a faked 429 sets', function (?int $retryAfter, int $seconds): void {
    $fake = new SteamFake()->tooManyRequests($retryAfter);
    $connector = fakedConnector($fake);
    $before = time();
    $lockedFor = function (SteamRateLimitException $exception) use ($before, $seconds): void {
        expect($exception->limit->getName())->toBe('SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:too_many_attempts_limit')
            ->and($exception->limit->getExpiryTimestamp())
            ->toBeGreaterThanOrEqual($before + $seconds)
            ->toBeLessThanOrEqual(time() + $seconds);
    };

    expect(fn (): int => $connector->players()->steamLevel(steamId()))->toThrow($lockedFor)
        ->and(fn (): int => $connector->players()->steamLevel(steamId()))->toThrow($lockedFor);

    $fake->assertSentCount(1);
})->with([
    'without Retry-After' => [null, 60],
    'with Retry-After' => [120, 120],
]);

test('a new fake lifts the lock a faked 429 left behind', function (): void {
    $throttled = fakedConnector(new SteamFake()->tooManyRequests());

    expect(fn (): int => $throttled->players()->steamLevel(steamId()))->toThrow(SteamRateLimitException::class);

    $connector = fakedConnector(new SteamFake()->players()->steamLevel(42));

    expect($connector->players()->steamLevel(steamId()))->toBe(42);
});

test('a plain mock client keeps the lock a faked 429 left behind', function (): void {
    $throttled = fakedConnector(new SteamFake()->tooManyRequests());

    expect(fn (): int => $throttled->players()->steamLevel(steamId()))->toThrow(SteamRateLimitException::class);

    $connector = fakedConnector(new MockClient([SteamConnector::class => MockResponse::make(['response' => ['player_level' => 42]])]));

    expect(fn (): int => $connector->players()->steamLevel(steamId()))->toThrow(SteamRateLimitException::class);
});

test('a spent budget refuses the next request with the key before it is sent', function (): void {
    $fake = new SteamFake()->players()->steamLevel(42);
    $connector = fakedConnector($fake);

    SteamFake::spendBudget($connector);

    expect(fn (): int => $connector->players()->steamLevel(steamId()))->toThrow(SteamRateLimitException::class)
        ->and($connector->rateLimitBudget()->remaining()[0]->left)->toBe(0);

    $fake->assertNothingSent();
});

test('a spent budget leaves requests sent without the key alone', function (): void {
    $connector = fakedConnector(new SteamFake()->stats()->currentPlayers(7));

    SteamFake::spendBudget($connector);

    expect($connector->stats()->currentPlayers(440))->toBe(7);
});

test('spending the budget spends every limit counting requests and leaves the lock after a 429 alone', function (): void {
    $connector = fakedConnector(new SteamFake, new SteamConfig('test-key', rateLimits: [
        'burst' => Limit::allow(5)->everySeconds(1),
        'day' => UtcDayLimit::requests(500_000),
    ]));

    SteamFake::spendBudget($connector);

    expect(array_map(static fn (Allowance $allowance): int => $allowance->left, $connector->rateLimitBudget()->remaining()))
        ->toBe(['burst' => 0, 'day' => 0])
        ->and($connector->rateLimitStore()->get('SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:too_many_attempts_limit'))->toBeNull();
});
