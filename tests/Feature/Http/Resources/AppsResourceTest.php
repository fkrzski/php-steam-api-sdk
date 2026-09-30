<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Resources\AppsResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(AppsResource::class);

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
