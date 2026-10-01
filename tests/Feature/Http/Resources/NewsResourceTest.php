<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Http\Resources\NewsResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(NewsResource::class);

test('appNews sends GetNewsForApp with every option and returns the DTO', function (): void {
    $mockClient = new MockClient([
        GetNewsForAppRequest::class => MockResponse::fixture('ISteamNews/GetNewsForApp/end-date'),
    ]);

    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    $news = $connector->news()->appNews(
        appId: 440,
        count: 3,
        maxLength: 0,
        endDate: new DateTimeImmutable('@1789754591'),
        feeds: ['tf2_blog'],
        tags: ['patchnotes'],
    );

    expect($news->total)->toBe(3937)
        ->and($news->items)->toHaveCount(3)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe([
            'appid' => 440,
            'count' => 3,
            'maxlength' => 0,
            'enddate' => 1789754591,
            'feeds' => 'tf2_blog',
            'tags' => 'patchnotes',
        ]);
});
