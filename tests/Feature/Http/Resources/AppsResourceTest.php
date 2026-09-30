<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Resources\AppsResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(AppsResource::class);

test('sdrConfig sends GetSDRConfig and returns the DTO', function (): void {
    $mockClient = new MockClient([
        GetSdrConfigRequest::class => MockResponse::fixture('ISteamApps/GetSDRConfig/default'),
    ]);

    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    $config = $connector->apps()->sdrConfig(730);

    expect($config)->toBeInstanceOf(SdrConfig::class)
        ->and($config->pointsOfPresence)->toHaveCount(3)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['appid' => 730]);
});

test('serversAtAddress sends GetServersAtAddress and returns the DTOs', function (): void {
    $mockClient = new MockClient([
        GetServersAtAddressRequest::class => MockResponse::fixture('ISteamApps/GetServersAtAddress/default'),
    ]);

    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    $servers = $connector->apps()->serversAtAddress('108.181.62.21');

    expect($servers)->toHaveCount(4)
        ->and($servers[0]->address)->toBe('108.181.62.21:27015')
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['addr' => '108.181.62.21']);
});

test('upToDateCheck sends UpToDateCheck and returns the DTO', function (): void {
    $mockClient = new MockClient([
        UpToDateCheckRequest::class => MockResponse::fixture('ISteamApps/UpToDateCheck/out-of-date'),
    ]);

    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    $check = $connector->apps()->upToDateCheck(440, 1);

    expect($check->isUpToDate)->toBeFalse()
        ->and($check->requiredVersion)->toBe(10828683)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['appid' => 440, 'version' => 1]);
});
