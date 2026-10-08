<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
use Fkrzski\SteamApiSdk\Http\Resources\WebApiResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(WebApiResource::class);

test('serverTime sends GetServerInfo and returns the date', function (): void {
    $mockClient = new MockClient([
        GetServerInfoRequest::class => MockResponse::fixture('ISteamWebAPIUtil/GetServerInfo/default'),
    ]);

    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    $serverTime = $connector->webApi()->serverTime();

    expect($serverTime->getTimestamp())->toBe(1791489578)
        ->and($mockClient->getLastRequest())->toBeInstanceOf(GetServerInfoRequest::class);
});

test('supportedApis sends GetSupportedAPIList and returns the interfaces', function (): void {
    $mockClient = new MockClient([
        GetSupportedApiListRequest::class => MockResponse::fixture('ISteamWebAPIUtil/GetSupportedAPIList/keyed'),
    ]);

    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    $interfaces = $connector->webApi()->supportedApis();

    expect($interfaces)->toHaveCount(3)
        ->and($interfaces[1]->name)->toBe('ISteamUser')
        ->and($mockClient->getLastRequest())->toBeInstanceOf(GetSupportedApiListRequest::class);
});
