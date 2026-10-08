<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(GetServerInfoRequest::class);

function serverInfoMock(): MockClient
{
    return new MockClient([
        GetServerInfoRequest::class => MockResponse::fixture('ISteamWebAPIUtil/GetServerInfo/default'),
    ]);
}

function sendServerInfoFixture(SteamConfig $config = new SteamConfig('test-key')): DateTimeImmutable
{
    $connector = new SteamConnector($config);
    $connector->withMockClient(serverInfoMock());

    /** @var DateTimeImmutable $serverTime */
    $serverTime = $connector->send(new GetServerInfoRequest)->dto();

    return $serverTime;
}

test('endpoint targets GetServerInfo v1', function (): void {
    expect((new GetServerInfoRequest)->resolveEndpoint())->toBe('/ISteamWebAPIUtil/GetServerInfo/v1/');
});

test('query carries no parameters', function (): void {
    expect((new GetServerInfoRequest)->query()->all())->toBe([]);
});

test('servertime comes back as a UTC DateTimeImmutable', function (): void {
    $serverTime = sendServerInfoFixture();

    expect($serverTime->getTimestamp())->toBe(1791489578)
        ->and($serverTime->getTimezone()->getName())->toBe('+00:00');
});

test('the configured key stays off the query', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $mockClient = serverInfoMock();
    $connector->withMockClient($mockClient);

    $connector->send(new GetServerInfoRequest);

    expect($mockClient->getLastPendingRequest()?->query()->all())->toBe([]);
});

test('a connector without an API key can send it', function (): void {
    expect(sendServerInfoFixture(new SteamConfig)->getTimestamp())->toBe(1791489578);
});
