<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\NewsItem;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\PlayerBadgesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGamesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\AppVersionCheckFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\GameServerFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrConfigFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\AppNewsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GameSchemaFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GlobalAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\AppsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\NewsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\PlayersFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\StatsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\UsersFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\WebApiFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

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
        fn (SteamFake $fake): SteamFake => $fake->webApi()->serverInfo(new DateTimeImmutable('2026-10-08 19:59:38 UTC')),
    ],
    'supported api list' => [fn (SteamFake $fake): SteamFake => $fake->webApi()->supportedApiList()],
]);

test('fakes every stats endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->stats()->userStats(UserStatsFactory::new()->gameName('Dead by Daylight'))
        ->stats()->achievements(PlayerAchievementsFactory::new()->achievements(
            PlayerAchievementFactory::new(),
            PlayerAchievementFactory::new()->locked(),
        ))
        ->stats()->currentPlayers(12_345)
        ->stats()->globalAchievements(
            GlobalAchievementFactory::new(),
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE'),
        )
        ->stats()->schema(GameSchemaFactory::new()->gameName('Portal 2'));
    $connector = fakedConnector($fake);

    $globalAchievements = $connector->stats()->globalAchievements(gameId: 381210);

    expect($connector->stats()->userStats(steamId(), appId: 381210)->gameName)->toBe('Dead by Daylight')
        ->and($connector->stats()->achievements(steamId(), appId: 381210)->achievements)->toHaveCount(2)
        ->and($connector->stats()->currentPlayers(appId: 381210))->toBe(12_345)
        ->and($globalAchievements)->toHaveCount(2)
        ->and($globalAchievements[1]->apiName)->toBe('ACH_ESCAPE')
        ->and($connector->stats()->schema(appId: 620)->gameName)->toBe('Portal 2');

    $fake->assertSentCount(5);
});

test('fakes every apps endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->apps()->upToDateCheck(AppVersionCheckFactory::new()->outOfDate())
        ->apps()->serversAtAddress(GameServerFactory::new(), GameServerFactory::new()->withoutSpectatorPort())
        ->apps()->sdrConfig(SdrConfigFactory::new()->revision(42));
    $connector = fakedConnector($fake);

    $servers = $connector->apps()->serversAtAddress('108.181.62.21');

    expect($connector->apps()->upToDateCheck(appId: 440, version: 1)->isUpToDate)->toBeFalse()
        ->and($servers)->toHaveCount(2)
        ->and($servers[1]->spectatorPort)->toBeNull()
        ->and($connector->apps()->sdrConfig(appId: 730)->revision)->toBe(42);

    $fake->assertSentCount(3);
});

test('fakes the news endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->news()->appNews(AppNewsFactory::new()->items(
            NewsItemFactory::new(),
            NewsItemFactory::new()->communityAnnouncement(),
        )->total(3939));

    $news = fakedConnector($fake)->news()->appNews(appId: 440, count: 2);

    expect($news->total)->toBe(3939)
        ->and($news->items)->toHaveCount(2)
        ->and($news->items[1]->isCommunityAnnouncement)->toBeTrue();

    $fake->assertSentCount(1);
});

test('fakes a whole news feed through the chain', function (): void {
    $fake = new SteamFake()->news()->newsFeed(...newsFeedItems(3));
    $connector = fakedConnector($fake);

    $first = $connector->news()->appNews(appId: 440, count: 2);
    $next = $connector->news()->appNews(appId: 440, count: 2, endDate: $first->items[1]->publishedAt);

    expect(array_map(static fn (NewsItem $item): string => $item->id, $first->items))->toBe(['1', '2'])
        ->and(array_map(static fn (NewsItem $item): string => $item->id, $next->items))->toBe(['2', '3']);

    $fake->assertSentCount(2);
});

test('fakes every web api endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->webApi()->serverInfo(new DateTimeImmutable('2026-10-08 19:59:38 UTC'))
        ->webApi()->supportedApiList(WebApiInterfaceFactory::new(), WebApiInterfaceFactory::new()->name('IGCVersion_440'));
    $connector = fakedConnector($fake);

    $interfaces = $connector->webApi()->supportedApis();

    expect($connector->webApi()->serverTime()->getTimestamp())->toBe(1791489578)
        ->and($interfaces)->toHaveCount(2)
        ->and($interfaces[1]->name)->toBe('IGCVersion_440');

    $fake->assertSentCount(2);
});

test('chains from the array form', function (): void {
    $connector = fakedConnector(new SteamFake([
        ResolveVanityUrlRequest::class => MockResponse::make(['response' => ['steamid' => '76561198000000000', 'success' => 1]]),
    ])->players()->steamLevel(42));

    expect($connector->users()->resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000')
        ->and($connector->players()->steamLevel(steamId()))->toBe(42);
});

test('raises what the sdk raises for a named failure', function (
    Closure $fail,
    Closure $call,
    string $exception,
    ?string $message = null,
): void {
    $fake = new SteamFake;
    $connector = fakedConnector($fake);

    expect($fail($fake))->toBe($fake)
        ->and(fn (): mixed => $call($connector))->toThrow($exception, $message);
})->with([
    'user stats not public' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->userStatsNotPublic(),
        fn (SteamConnector $connector): UserStats => $connector->stats()->userStats(steamId(), appId: 381210),
        ProfileNotPublicException::class,
    ],
    'achievements unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->achievementsUnavailable(),
        fn (SteamConnector $connector): PlayerAchievements => $connector->stats()->achievements(steamId(), appId: 381210),
        StatsUnavailableException::class,
    ],
    'current players app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->currentPlayersAppNotFound(),
        fn (SteamConnector $connector): int => $connector->stats()->currentPlayers(appId: 1),
        AppNotFoundException::class,
    ],
    'global achievements unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->globalAchievementsUnavailable(),
        fn (SteamConnector $connector): array => $connector->stats()->globalAchievements(gameId: 381210),
        StatsUnavailableException::class,
    ],
    'schema app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->stats()->schemaAppNotFound(),
        fn (SteamConnector $connector): GameSchema => $connector->stats()->schema(appId: 1),
        AppNotFoundException::class,
    ],
    'up to date check unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->upToDateCheckUnavailable(),
        fn (SteamConnector $connector): AppVersionCheck => $connector->apps()->upToDateCheck(appId: 999999999, version: 1),
        AppVersionUnavailableException::class,
    ],
    'servers at address invalid' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressInvalid(),
        fn (SteamConnector $connector): array => $connector->apps()->serversAtAddress('not-an-ip'),
        InvalidServerAddressException::class,
    ],
    // InvalidServerAddressException is a SteamApiException too, so the message tells them apart.
    'servers at address refused' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressRefused(),
        fn (SteamConnector $connector): array => $connector->apps()->serversAtAddress('127.0.0.1'),
        SteamApiException::class,
        'once per minute for a given IP',
    ],
    'sdr config app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfigAppNotFound(),
        fn (SteamConnector $connector): SdrConfig => $connector->apps()->sdrConfig(appId: 999999999),
        AppNotFoundException::class,
    ],
    'app news unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->news()->appNewsUnavailable(),
        fn (SteamConnector $connector): AppNews => $connector->news()->appNews(appId: 480),
        AppNewsUnavailableException::class,
    ],
    'supported api list invalid key' => [
        fn (SteamFake $fake): SteamFake => $fake->webApi()->supportedApiListInvalidKey(),
        fn (SteamConnector $connector): array => $connector->webApi()->supportedApis(),
        InvalidApiKeyException::class,
    ],
]);

test('fails every request without its own response', function (Closure $fail, string $exception): void {
    $fake = new SteamFake()->players()->steamLevel(42);
    $connector = fakedConnector($fake);

    expect($fail($fake))->toBe($fake)
        ->and($connector->players()->steamLevel(steamId()))->toBe(42)
        ->and(fn (): array => $connector->users()->summaries([steamId()]))->toThrow($exception);
})->with([
    'invalid api key' => [
        fn (SteamFake $fake): SteamFake => $fake->invalidApiKey(),
        InvalidApiKeyException::class,
    ],
    'connection failed' => [
        fn (SteamFake $fake): SteamFake => $fake->connectionFailed(),
        SteamConnectionException::class,
    ],
    'too many requests' => [
        fn (SteamFake $fake): SteamFake => $fake->tooManyRequests(),
        SteamRateLimitException::class,
    ],
]);

test('refuses requests locally for as long as a faked 429 says', function (): void {
    $fake = new SteamFake()->tooManyRequests(120);
    $connector = fakedConnector($fake);
    $before = time();

    expect(fn (): int => $connector->players()->steamLevel(steamId()))->toThrow(SteamRateLimitException::class)
        ->and(fn (): int => $connector->players()->steamLevel(steamId()))
        ->toThrow(function (SteamRateLimitException $exception) use ($before): void {
            expect($exception->limit->getExpiryTimestamp())
                ->toBeGreaterThanOrEqual($before + 120)
                ->toBeLessThanOrEqual(time() + 120);
        });

    $fake->assertSentCount(1);
});

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
