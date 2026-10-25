<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\AppVersionCheckFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\GameServerFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrConfigFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrPointOfPresenceFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\AppsFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Request;

covers(AppsFake::class);

// Envelopes

test('flags the version check as successful', function (): void {
    $answer = answerFrom(new SteamFake()->apps()->upToDateCheck(AppVersionCheckFactory::new()->outOfDate()), new UpToDateCheckRequest(440, 1));

    expect($answer->body())->toBe(json_encode([
        'response' => [
            'success' => true,
            ...AppVersionCheckFactory::new()->outOfDate()->toArray(),
        ],
    ], JSON_THROW_ON_ERROR));
});

test('flags the server list as successful', function (): void {
    $answer = answerFrom(
        new SteamFake()->apps()->serversAtAddress(GameServerFactory::new(), GameServerFactory::new()->address('108.181.62.21:27025')),
        new GetServersAtAddressRequest('108.181.62.21'),
    );

    expect($answer->body())->toBe(json_encode([
        'response' => [
            'success' => true,
            'servers' => [
                GameServerFactory::new()->toArray(),
                GameServerFactory::new()->address('108.181.62.21:27025')->toArray(),
            ],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('puts the sdr config at the top level beside a success flag', function (): void {
    $answer = answerFrom(new SteamFake()->apps()->sdrConfig(SdrConfigFactory::new()), new GetSdrConfigRequest(730));

    expect($answer->body())->toBe(json_encode([
        ...SdrConfigFactory::new()->toArray(),
        'success' => true,
    ], JSON_THROW_ON_ERROR));
});

test('sends the body steam was captured sending', function (Closure $fake, Request $request, string $fixture): void {
    $answer = answerFrom($fake(new SteamFake), $request);

    expect([$answer->status(), $answer->body()])->toBe(captured($fixture));
})->with([
    'upToDateCheckUnavailable()' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->upToDateCheckUnavailable(),
        new UpToDateCheckRequest(999999999, 1),
        'ISteamApps/UpToDateCheck/unavailable',
    ],
    'serversAtAddressInvalid()' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressInvalid(),
        new GetServersAtAddressRequest('not-an-ip'),
        'ISteamApps/GetServersAtAddress/invalid-address',
    ],
    'serversAtAddressRefused()' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressRefused(),
        new GetServersAtAddressRequest('127.0.0.1'),
        'ISteamApps/GetServersAtAddress/refused',
    ],
    'sdrConfigAppNotFound()' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfigAppNotFound(),
        new GetSdrConfigRequest(999999999),
        'ISteamApps/GetSDRConfig/unknown-app',
    ],
    'serversAtAddress() of no servers' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddress(),
        new GetServersAtAddressRequest('127.0.0.1'),
        'ISteamApps/GetServersAtAddress/empty',
    ],
]);

// Round trips through the connector

test('fakes every apps endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->apps()->upToDateCheck(AppVersionCheckFactory::new()->outOfDate())
        ->apps()->serversAtAddress(GameServerFactory::new(), GameServerFactory::new()->withoutSpectatorPort())
        ->apps()->sdrConfig(SdrConfigFactory::new()->revision(42)->pointsOfPresence(
            SdrPointOfPresenceFactory::new()->code('waw')->latitude(52.22)->longitude(21),
            SdrPointOfPresenceFactory::new()->code('eat')->aliases('mwh')->withoutRelays(),
        ));
    $connector = fakedConnector($fake);

    $check = $connector->apps()->upToDateCheck(appId: 440, version: 1);
    $servers = $connector->apps()->serversAtAddress('108.181.62.21');
    $config = $connector->apps()->sdrConfig(appId: 730);

    expect($check->isUpToDate)->toBeFalse()
        ->and($check->requiredVersion)->toBe(10828683)
        ->and($check->message)->toBe('Your server is out of date, please upgrade')
        ->and($servers)->toHaveCount(2)
        ->and($servers[0]->address)->toBe('108.181.62.21:27015')
        ->and($servers[1]->spectatorPort)->toBeNull()
        ->and($config->revision)->toBe(42)
        ->and(array_keys($config->pointsOfPresence))->toBe(['waw', 'eat'])
        ->and($config->pointsOfPresence['waw']->latitude)->toBe(52.22)
        ->and($config->pointsOfPresence['waw']->longitude)->toBe(21.0)
        ->and($config->pointsOfPresence['eat']->aliases)->toBe(['mwh'])
        ->and($config->pointsOfPresence['eat']->relays)->toBeEmpty();

    $fake->assertSentCount(3);
});

// Failures — these pin the contract with the SDK's exception mapping

test('raises what the sdk raises for a named failure', function (
    Closure $fail,
    Closure $call,
    string $exception,
    ?string $message = null,
): void {
    $fake = new SteamFake;
    $connector = fakedConnector($fake);

    expect($fail($fake))->toBe($fake)
        ->and(fn (): mixed => $call($connector))->toThrow($exception, $message);
})->with([
    'up to date check unavailable' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->upToDateCheckUnavailable(),
        fn (SteamConnector $connector): AppVersionCheck => $connector->apps()->upToDateCheck(appId: 999999999, version: 1),
        AppVersionUnavailableException::class,
    ],
    'servers at address invalid' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressInvalid(),
        fn (SteamConnector $connector): array => $connector->apps()->serversAtAddress('not-an-ip'),
        InvalidServerAddressException::class,
    ],
    // InvalidServerAddressException is a SteamApiException too, so the message tells them apart.
    'servers at address refused' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddressRefused(),
        fn (SteamConnector $connector): array => $connector->apps()->serversAtAddress('127.0.0.1'),
        SteamApiException::class,
        'once per minute for a given IP',
    ],
    'sdr config app not found' => [
        fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfigAppNotFound(),
        fn (SteamConnector $connector): SdrConfig => $connector->apps()->sdrConfig(appId: 999999999),
        AppNotFoundException::class,
        'No Steam app found for app ID 999999999.',
    ],
]);

test('claims an app the sdr config does not know before the retry loop', function (): void {
    $fake = new SteamFake()->apps()->sdrConfigAppNotFound();
    $connector = fakedConnector($fake, new SteamConfig('test-key', tries: 3));

    expect(fn (): SdrConfig => $connector->apps()->sdrConfig(appId: 999999999))
        ->toThrow(AppNotFoundException::class);

    $fake->assertSentCount(1);
});
