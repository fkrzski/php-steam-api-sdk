<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([ResolveVanityUrlRequest::class, SteamUserNotFoundException::class]);

function connector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

test('endpoint targets ResolveVanityURL v1', function (): void {
    $request = new ResolveVanityUrlRequest('someNick');

    expect($request->resolveEndpoint())->toBe('/ISteamUser/ResolveVanityURL/v1/');
});

test('query carries vanityurl parameter', function (): void {
    $request = new ResolveVanityUrlRequest('someNick');

    expect($request->query()->all())->toBe(['vanityurl' => 'someNick']);
});

test('success response yields SteamId value object', function (): void {
    $mock = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]);

    $connector = connector();
    $connector->withMockClient($mock);

    $dto = $connector->send(new ResolveVanityUrlRequest('any'))->dto();

    expect($dto)->toBeInstanceOf(SteamId::class)
        ->and($dto->value)->toBe('76561198000000000');
});

test('not-found response throws SteamUserNotFoundException', function (): void {
    $mock = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/not_found'),
    ]);

    $connector = connector();
    $connector->withMockClient($mock);

    expect(fn (): mixed => $connector->send(new ResolveVanityUrlRequest('missingUser'))->dto())
        ->toThrow(SteamUserNotFoundException::class, 'No Steam user found for vanity name "missingUser".');
});

test('non-success code with a steamid present still throws not found', function (): void {
    $mock = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::make([
            'response' => ['success' => 42, 'steamid' => '76561198000000000'],
        ]),
    ]);

    $connector = connector();
    $connector->withMockClient($mock);

    expect(fn (): mixed => $connector->send(new ResolveVanityUrlRequest('missingUser'))->dto())
        ->toThrow(SteamUserNotFoundException::class);
});
