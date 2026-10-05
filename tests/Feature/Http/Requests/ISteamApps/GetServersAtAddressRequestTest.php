<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\GameServer;
use Fkrzski\SteamApiSdk\Enums\ServerRegion;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetServersAtAddressRequest::class, GameServer::class, InvalidServerAddressException::class, SteamApiException::class]);

function serversAtAddressMock(string $fixture): MockClient
{
    return new MockClient([
        GetServersAtAddressRequest::class => MockResponse::fixture(sprintf('ISteamApps/GetServersAtAddress/%s', $fixture)),
    ]);
}

/**
 * @return list<GameServer>
 */
function sendServersAtAddressFixture(
    string $fixture,
    string $address = '108.181.62.21',
    SteamConfig $config = new SteamConfig('test-key'),
): array {
    $connector = new SteamConnector($config);
    $connector->withMockClient(serversAtAddressMock($fixture));

    /** @var list<GameServer> $servers */
    $servers = $connector->send(new GetServersAtAddressRequest($address))->dto();

    return $servers;
}

test('endpoint targets GetServersAtAddress v1', function (): void {
    $request = new GetServersAtAddressRequest('108.181.62.21');

    expect($request->resolveEndpoint())->toBe('/ISteamApps/GetServersAtAddress/v1/');
});

test('query carries the address as addr', function (): void {
    $request = new GetServersAtAddressRequest('108.181.62.21:27015');

    expect($request->query()->all())->toBe(['addr' => '108.181.62.21:27015']);
});

test('every server at the address maps to a GameServer', function (): void {
    $servers = sendServersAtAddressFixture('default');

    expect($servers)->toHaveCount(4)
        ->and($servers[0]->address)->toBe('108.181.62.21:27015')
        ->and($servers[0]->steamId->value)->toBe('85568392924469984')
        ->and($servers[0]->appId)->toBe(440)
        ->and($servers[0]->gameDir)->toBe('tf')
        ->and($servers[0]->region)->toBe(ServerRegion::UsEast)
        ->and($servers[0]->isSecure)->toBeTrue()
        ->and($servers[0]->isLan)->toBeFalse()
        ->and($servers[0]->gamePort)->toBe(27015)
        ->and($servers[0]->spectatorPort)->toBe(27016)
        ->and($servers[3]->address)->toBe('108.181.62.21:27045');
});

test('a server without SourceTV and a region carries neither', function (): void {
    [$server] = sendServersAtAddressFixture('rust', '216.39.241.176');

    expect($server->address)->toBe('216.39.241.176:28015')
        ->and($server->steamId->value)->toBe('90293757517545488')
        ->and($server->appId)->toBe(252490)
        ->and($server->region)->toBe(ServerRegion::World)
        ->and($server->gamePort)->toBe(28010)
        ->and($server->spectatorPort)->toBeNull();
});

test('an address with no servers returns an empty list', function (): void {
    expect(sendServersAtAddressFixture('empty', '208.64.200.52'))->toBe([]);
});

test('an address Steam rejects throws InvalidServerAddressException', function (): void {
    sendServersAtAddressFixture('invalid-address', 'not-an-ip');
})->throws(
    InvalidServerAddressException::class,
    'Steam rejected "not-an-ip" as a server address: it takes an IPv4 address, optionally with a query port.',
);

test('the rejected address carries the 200 Steam answered with', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(serversAtAddressMock('invalid-address'));

    $response = $connector->send(new GetServersAtAddressRequest('not-an-ip'));

    try {
        $response->dto();
    } catch (InvalidServerAddressException $invalidServerAddressException) {
        expect($invalidServerAddressException->response)->toBe($response)
            ->and($invalidServerAddressException->getCode())->toBe(200);

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

test('a lookup Steam refuses surfaces its message on the root exception', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(serversAtAddressMock('refused'));

    $response = $connector->send(new GetServersAtAddressRequest('127.0.0.1'));

    try {
        $response->dto();
    } catch (SteamApiException $steamApiException) {
        expect($steamApiException::class)->toBe(SteamApiException::class)
            ->and($steamApiException->getMessage())->toBe("GetServersAtAddress: Steam API request failed: Please don't call this API more often than once per minute for a given IP.")
            ->and($steamApiException->response)->toBe($response)
            ->and($steamApiException->getCode())->toBe(200);

        return;
    }

    throw new RuntimeException('Expected the lookup to fail.');
});

test('an empty address is left to the connector', function (): void {
    sendServersAtAddressFixture('empty-address', '');
})->throws(SteamApiException::class, 'Steam API request failed with HTTP 400.');

test('the configured key stays off the query', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $mockClient = serversAtAddressMock('default');
    $connector->withMockClient($mockClient);

    $connector->send(new GetServersAtAddressRequest('108.181.62.21'));

    expect($mockClient->getLastPendingRequest()?->query()->all())->toBe(['addr' => '108.181.62.21']);
});

test('a connector without an API key can send it', function (): void {
    $servers = sendServersAtAddressFixture('default', config: new SteamConfig);

    expect($servers)->toHaveCount(4);
});
