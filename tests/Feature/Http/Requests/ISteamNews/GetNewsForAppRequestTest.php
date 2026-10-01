<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Dto\NewsItem;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetNewsForAppRequest::class, AppNews::class, NewsItem::class, AppNewsUnavailableException::class]);

function newsForAppMock(string $fixture): MockClient
{
    return new MockClient([
        GetNewsForAppRequest::class => MockResponse::fixture(sprintf('ISteamNews/GetNewsForApp/%s', $fixture)),
    ]);
}

function sendNewsForAppFixture(
    string $fixture,
    int $appId = 440,
    SteamConfig $config = new SteamConfig('test-key'),
): AppNews {
    $connector = new SteamConnector($config);
    $connector->withMockClient(newsForAppMock($fixture));

    /** @var AppNews $news */
    $news = $connector->send(new GetNewsForAppRequest($appId))->dto();

    return $news;
}

test('endpoint targets GetNewsForApp v2', function (): void {
    $request = new GetNewsForAppRequest(440);

    expect($request->resolveEndpoint())->toBe('/ISteamNews/GetNewsForApp/v2/');
});

test('query carries only appid by default', function (): void {
    $request = new GetNewsForAppRequest(440);

    expect($request->query()->all())->toBe(['appid' => 440]);
});

test('query carries every option, with the filters comma-joined', function (): void {
    $request = new GetNewsForAppRequest(
        appId: 440,
        count: 3,
        maxLength: 100,
        endDate: new DateTimeImmutable('@1789754591'),
        feeds: ['tf2_blog', 'steam_community_announcements'],
        tags: ['hide_library_overview', 'hide_library_detail'],
    );

    expect($request->query()->all())->toBe([
        'appid' => 440,
        'count' => 3,
        'maxlength' => 100,
        'enddate' => 1789754591,
        'feeds' => 'tf2_blog,steam_community_announcements',
        'tags' => 'hide_library_overview,hide_library_detail',
    ]);
});

test('a zero count and length stay on the query', function (): void {
    $request = new GetNewsForAppRequest(440, count: 0, maxLength: 0);

    expect($request->query()->all())->toBe(['appid' => 440, 'count' => 0, 'maxlength' => 0]);
});

test('news maps the items and the total Steam counts for the filter', function (): void {
    $news = sendNewsForAppFixture('default');
    $item = $news->items[0];

    expect($news->appId)->toBe(440)
        ->and($news->total)->toBe(3939)
        ->and($news->items)->toHaveCount(3)
        ->and($item->id)->toBe('1844115010502391')
        ->and($item->title)->toBe('Art Pass Contest')
        ->and($item->url)->toBe('https://steamstore-a.akamaihd.net/news/externalpost/steam_community_announcements/1844115010502391')
        ->and($item->isExternalUrl)->toBeTrue()
        ->and($item->author)->toBe('erics')
        ->and($item->contents)->toStartWith('[img]{STEAM_CLAN_LOC_IMAGE}/554111/')
        ->and($item->feedLabel)->toBe('Community Announcements')
        ->and($item->feedName)->toBe('steam_community_announcements')
        ->and($item->isCommunityAnnouncement)->toBeTrue()
        ->and($item->publishedAt->getTimestamp())->toBe(1790020691)
        ->and($item->appId)->toBe(440)
        ->and($item->tags)->toBe(['mod_reviewed', 'ModAct_1447510170_1790021217_0', 'ModAct_1447510170_1790022585_4', 'mod_require_rereview']);
});

test('an item from an outside feed carries its own markup, no author and no tags', function (): void {
    $item = sendNewsForAppFixture('default')->items[1];

    expect($item->feedName)->toBe('tf2_blog')
        ->and($item->feedLabel)->toBe('TF2 Blog')
        ->and($item->isCommunityAnnouncement)->toBeFalse()
        ->and($item->author)->toBeNull()
        ->and($item->tags)->toBe([])
        ->and($item->contents)->toStartWith('<a href="https://discord.gg/y9b4QeFWv" target="_blank"><img src=');
});

test('a positive max length flattens the body into an excerpt', function (): void {
    [$announcement, $blogPost] = sendNewsForAppFixture('excerpt')->items;

    expect($announcement->contents)->toBe('{STEAM_CLAN_LOC_IMAGE}/554111/d651bb0462a39f27ab9058916f4fc4a3212c6f13.png (Image credit: Concept - ...')
        ->and($blogPost->contents)->not->toContain('<img')
        ->and($blogPost->contents)->toContain('<a href="https://steamcommunity.com/profiles/76561198914938199/" target="_blank">Endi</a>')
        ->and($blogPost->contents)->toEndWith('commun...');
});

test('the end date is inclusive, so the next page starts on the last item', function (): void {
    $lastOfPage = sendNewsForAppFixture('default')->items[2];
    $nextPage = sendNewsForAppFixture('end-date');

    expect($nextPage->items[0]->id)->toBe($lastOfPage->id)
        ->and($nextPage->items[0]->publishedAt)->toEqual($lastOfPage->publishedAt)
        ->and($nextPage->total)->toBe(3937);
});

test('an unknown feed name filters nothing out', function (): void {
    $news = sendNewsForAppFixture('unknown-feed');

    expect($news->total)->toBe(3939)
        ->and(array_map(static fn (NewsItem $item): string => $item->feedName, $news->items))
        ->toBe(['steam_community_announcements', 'tf2_blog', 'steam_community_announcements']);
});

test('an unknown tag filters everything out', function (): void {
    $news = sendNewsForAppFixture('unknown-tag');

    expect($news->items)->toBe([])
        ->and($news->total)->toBe(0);
});

test('a DLC gets the news of its parent game', function (): void {
    $news = sendNewsForAppFixture('parent-app', 2778580);

    expect($news->appId)->toBe(2778580)
        ->and($news->items[0]->appId)->toBe(1245620);
});

test('an app without news returns no items', function (): void {
    $news = sendNewsForAppFixture('no-news', 3000000);

    expect($news->appId)->toBe(3000000)
        ->and($news->items)->toBe([])
        ->and($news->total)->toBe(0);
});

test('Steam withholding the news throws AppNewsUnavailableException', function (int $appId): void {
    expect(fn (): AppNews => sendNewsForAppFixture('unavailable', $appId))->toThrow(
        AppNewsUnavailableException::class,
        sprintf('Steam returned no news for app %d: no app has that ID, or Steam does not publish its news.', $appId),
    );
})->with([
    'an app ID Steam does not know' => 999999999,
    'an app that exists' => 480,
]);

test('the withheld news carries the 403 Steam answered with', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(newsForAppMock('unavailable'));

    try {
        $connector->send(new GetNewsForAppRequest(480));
    } catch (AppNewsUnavailableException $appNewsUnavailableException) {
        expect($appNewsUnavailableException->getCode())->toBe(403);

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

test('a failure other than the 403 is left to the connector', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetNewsForAppRequest::class => MockResponse::make('{}', 500),
    ]));

    $connector->send(new GetNewsForAppRequest(440));
})->throws(
    SteamApiException::class,
    'Steam API request failed with HTTP 500.',
);

test('the configured key stays off the query', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $mockClient = newsForAppMock('default');
    $connector->withMockClient($mockClient);

    $connector->send(new GetNewsForAppRequest(440));

    expect($mockClient->getLastPendingRequest()?->query()->all())->toBe(['appid' => 440]);
});

test('a connector without an API key can send it', function (): void {
    $news = sendNewsForAppFixture('default', config: new SteamConfig);

    expect($news->items)->toHaveCount(3);
});
