<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Dto\SdrPointOfPresence;
use Fkrzski\SteamApiSdk\Dto\SdrRelay;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetSdrConfigRequest::class, SdrConfig::class, SdrPointOfPresence::class, SdrRelay::class, AppNotFoundException::class]);

function sdrConfigMock(string $fixture): MockClient
{
    return new MockClient([
        GetSdrConfigRequest::class => MockResponse::fixture(sprintf('ISteamApps/GetSDRConfig/%s', $fixture)),
    ]);
}

function sendSdrConfigFixture(
    string $fixture,
    int $appId = 730,
    SteamConfig $config = new SteamConfig('test-key'),
): SdrConfig {
    $connector = new SteamConnector($config);
    $connector->withMockClient(sdrConfigMock($fixture));

    /** @var SdrConfig $sdrConfig */
    $sdrConfig = $connector->send(new GetSdrConfigRequest($appId))->dto();

    return $sdrConfig;
}

test('endpoint targets GetSDRConfig v1', function (): void {
    $request = new GetSdrConfigRequest(730);

    expect($request->resolveEndpoint())->toBe('/ISteamApps/GetSDRConfig/v1/');
});

test('query carries appid parameter', function (): void {
    $request = new GetSdrConfigRequest(730);

    expect($request->query()->all())->toBe(['appid' => 730]);
});

test('every point of presence maps under its own code', function (): void {
    $config = sendSdrConfigFixture('default');
    $amsterdam = $config->pointsOfPresence['ams'];

    expect($config->revision)->toBe(1790374330)
        ->and(array_keys($config->pointsOfPresence))->toBe(['ams', 'eat', 'waw'])
        ->and($amsterdam->code)->toBe('ams')
        ->and($amsterdam->description)->toBe('Amsterdam (Netherlands)')
        ->and($amsterdam->latitude)->toBe(52.37)
        ->and($amsterdam->longitude)->toBe(4.9)
        ->and($amsterdam->aliases)->toBe([])
        ->and($amsterdam->relays)->toHaveCount(2)
        ->and($amsterdam->relays[1]->ipv4)->toBe('155.133.248.37')
        ->and($amsterdam->relays[1]->minPort)->toBe(27015)
        ->and($amsterdam->relays[1]->maxPort)->toBe(27060);
});

test('a whole-degree coordinate comes back as a float', function (): void {
    $warsaw = sendSdrConfigFixture('default')->pointsOfPresence['waw'];

    expect($warsaw->longitude)->toBe(21.0)
        ->and($warsaw->latitude)->toBe(52.22);
});

test('a point of presence that only routes carries no relays', function (): void {
    $wenatchee = sendSdrConfigFixture('default')->pointsOfPresence['eat'];

    expect($wenatchee->code)->toBe('eat')
        ->and($wenatchee->aliases)->toBe(['mwh'])
        ->and($wenatchee->relays)->toBe([]);
});

test('app ID zero yields the whole relay network', function (): void {
    $config = sendSdrConfigFixture('steam-wide', 0);

    expect(array_keys($config->pointsOfPresence))->toBe(['ams', 'aae1'])
        ->and($config->pointsOfPresence['ams']->relays[0]->ipv4)->toBe('155.133.248.36')
        ->and($config->pointsOfPresence['aae1']->description)->toBe('Amazon ap-east-1 (Hong Kong)')
        ->and($config->pointsOfPresence['aae1']->relays)->toBe([]);
});

test('unknown app throws AppNotFoundException', function (): void {
    sendSdrConfigFixture('unknown-app', 999999999);
})->throws(
    AppNotFoundException::class,
    'No Steam app found for app ID 999999999.',
);

test('the unknown app carries the 500 Steam answered with', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(sdrConfigMock('unknown-app'));

    try {
        $connector->send(new GetSdrConfigRequest(999999999));
    } catch (AppNotFoundException $appNotFoundException) {
        expect($appNotFoundException->getCode())->toBe(500)
            ->and($appNotFoundException->response?->json('message'))->toBe('Failed to get appinfo');

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

test('an unknown app is not retried', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key', tries: 3));
    $connector->withMockClient($mockClient = sdrConfigMock('unknown-app'));

    expect(fn (): mixed => $connector->send(new GetSdrConfigRequest(999999999)))
        ->toThrow(AppNotFoundException::class);

    $mockClient->assertSentCount(1);
});

test('a failure other than the appinfo one is left to the connector', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetSdrConfigRequest::class => MockResponse::make([], 500),
    ]));

    $connector->send(new GetSdrConfigRequest(730));
})->throws(
    SteamApiException::class,
    'Steam API request failed with HTTP 500.',
);

test('the configured key stays off the query', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $mockClient = sdrConfigMock('default');
    $connector->withMockClient($mockClient);

    $connector->send(new GetSdrConfigRequest(730));

    expect($mockClient->getLastPendingRequest()?->query()->all())->toBe(['appid' => 730]);
});

test('a connector without an API key can send it', function (): void {
    $config = sendSdrConfigFixture('default', config: new SteamConfig);

    expect($config->pointsOfPresence)->toHaveCount(3);
});
