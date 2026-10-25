<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\AppNewsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\NewsFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Request;

covers(NewsFake::class);

// Envelopes

test('nests app news under the appnews key', function (): void {
    $answer = answerFrom(
        new SteamFake()->news()->appNews(AppNewsFactory::new()->items(NewsItemFactory::new())->total(3939)),
        new GetNewsForAppRequest(440),
    );

    expect($answer->body())->toBe(json_encode([
        'appnews' => [
            'appid' => 440,
            'newsitems' => [NewsItemFactory::new()->toArray()],
            'count' => 3939,
        ],
    ], JSON_THROW_ON_ERROR));
});

test('sends the body steam was captured sending', function (Closure $fake, Request $request, string $fixture): void {
    $answer = answerFrom($fake(new SteamFake), $request);

    expect([$answer->status(), $answer->body()])->toBe(captured($fixture));
})->with([
    'appNewsUnavailable()' => [
        fn (SteamFake $fake): SteamFake => $fake->news()->appNewsUnavailable(),
        new GetNewsForAppRequest(480),
        'ISteamNews/GetNewsForApp/unavailable',
    ],
    'appNews() of no news' => [
        fn (SteamFake $fake): SteamFake => $fake->news()->appNews(AppNewsFactory::new()->appId(3000000)->items()->total(0)),
        new GetNewsForAppRequest(3000000),
        'ISteamNews/GetNewsForApp/no-news',
    ],
]);

// Round trips through the connector

test('fakes the news endpoint through the chain', function (): void {
    $fake = new SteamFake()->news()->appNews(
        AppNewsFactory::new()
            ->items(
                NewsItemFactory::new()->communityAnnouncement()->tags('patchnotes'),
                NewsItemFactory::new()->withoutAuthor(),
            )
            ->total(3939),
    );

    $news = fakedConnector($fake)->news()->appNews(appId: 440, count: 2);

    expect($news->total)->toBe(3939)
        ->and($news->items)->toHaveCount(2)
        ->and($news->items[0]->isCommunityAnnouncement)->toBeTrue()
        ->and($news->items[0]->tags)->toBe(['patchnotes'])
        ->and($news->items[1]->author)->toBeNull()
        ->and($news->items[1]->tags)->toBeEmpty();

    $fake->assertSentCount(1);
});

test('pages a news feed the way steam does', function (): void {
    $items = newsFeedItems(3);
    $fake = new SteamFake()->news()->newsFeed(...$items);
    $connector = fakedConnector($fake);

    $first = $connector->news()->appNews(appId: 570, count: 2);
    $next = $connector->news()->appNews(appId: 570, count: 2, endDate: $items[1]->make()->publishedAt);

    expect($first->appId)->toBe(570)
        ->and(array_column($first->items, 'id'))->toBe(['1', '2'])
        ->and($first->total)->toBe(3)
        ->and(array_column($next->items, 'id'))->toBe(['2', '3'])
        ->and($next->total)->toBe(2);

    $fake->assertSentCount(2);
});

test('answers a news feed request without a count with twenty items', function (): void {
    $news = fakedConnector(new SteamFake()->news()->newsFeed(...newsFeedItems(21)))->news()->appNews(appId: 440);

    expect($news->items)->toHaveCount(20)
        ->and($news->total)->toBe(21);
});

test('answers an empty news feed with no news', function (): void {
    $news = fakedConnector(new SteamFake()->news()->newsFeed())->news()->appNews(appId: 440);

    expect($news->items)->toBeEmpty()
        ->and($news->total)->toBe(0);
});

// Failures — these pin the contract with the SDK's exception mapping

test('raises unavailable news from a 403 on the news endpoint', function (): void {
    $fake = new SteamFake;

    expect($fake->news()->appNewsUnavailable())->toBe($fake)
        ->and(fn (): AppNews => fakedConnector($fake)->news()->appNews(appId: 480))
        ->toThrow(AppNewsUnavailableException::class, 'GetNewsForApp: Steam returned no news for app 480');
});
