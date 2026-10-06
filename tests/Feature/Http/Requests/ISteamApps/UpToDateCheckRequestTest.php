<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([UpToDateCheckRequest::class, AppVersionCheck::class, AppVersionUnavailableException::class]);

function upToDateCheckMock(string $fixture): MockClient
{
    return new MockClient([
        UpToDateCheckRequest::class => MockResponse::fixture(sprintf('ISteamApps/UpToDateCheck/%s', $fixture)),
    ]);
}

function sendUpToDateCheckFixture(
    string $fixture,
    int $appId = 440,
    int $version = 1,
    SteamConfig $config = new SteamConfig('test-key'),
): AppVersionCheck {
    $connector = new SteamConnector($config);
    $connector->withMockClient(upToDateCheckMock($fixture));

    /** @var AppVersionCheck $check */
    $check = $connector->send(new UpToDateCheckRequest($appId, $version))->dto();

    return $check;
}

test('endpoint targets UpToDateCheck v1', function (): void {
    $request = new UpToDateCheckRequest(440, 10828683);

    expect($request->resolveEndpoint())->toBe('/ISteamApps/UpToDateCheck/v1/');
});

test('query carries appid and version parameters', function (): void {
    $request = new UpToDateCheckRequest(440, 10828683);

    expect($request->query()->all())->toBe(['appid' => 440, 'version' => 10828683]);
});

test('the current version is up to date and carries no requirement', function (): void {
    $check = sendUpToDateCheckFixture('up-to-date', version: 10828683);

    expect($check->isUpToDate)->toBeTrue()
        ->and($check->isListable)->toBeTrue()
        ->and($check->requiredVersion)->toBeNull()
        ->and($check->message)->toBeNull();
});

test('an older version carries the required version and the message', function (): void {
    $check = sendUpToDateCheckFixture('out-of-date');

    expect($check->isUpToDate)->toBeFalse()
        ->and($check->isListable)->toBeFalse()
        ->and($check->requiredVersion)->toBe(10828683)
        ->and($check->message)->toBe('Your server is out of date, please upgrade');
});

test('an app Steam cannot check throws AppVersionUnavailableException', function (int $appId): void {
    expect(fn (): AppVersionCheck => sendUpToDateCheckFixture('unavailable', $appId))->toThrow(
        AppVersionUnavailableException::class,
        sprintf('Steam cannot check app %d for updates: no app has that ID, or it publishes no server version.', $appId),
    );
})->with([
    'an app without versioned servers' => 620,
    'an app ID Steam does not know' => 999999999,
]);

test('the unavailable check carries the 200 Steam answered with', function (): void {
    $mockClient = upToDateCheckMock('unavailable');
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    try {
        $connector->send(new UpToDateCheckRequest(620, 1));
    } catch (AppVersionUnavailableException $appVersionUnavailableException) {
        expect($appVersionUnavailableException->response)->toBe($mockClient->getLastResponse())
            ->and($appVersionUnavailableException->getCode())->toBe(200);

        return;
    }

    throw new RuntimeException('Expected the check to fail.');
});

test('an app ID Steam cannot route is left to the connector', function (): void {
    sendUpToDateCheckFixture('app-id-zero', appId: 0);
})->throws(SteamApiException::class, 'Steam API request failed with HTTP 400.');

test('the configured key stays off the query', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $mockClient = upToDateCheckMock('out-of-date');
    $connector->withMockClient($mockClient);

    $connector->send(new UpToDateCheckRequest(440, 1));

    expect($mockClient->getLastPendingRequest()?->query()->all())->toBe(['appid' => 440, 'version' => 1]);
});

test('a connector without an API key can send it', function (): void {
    $check = sendUpToDateCheckFixture('out-of-date', config: new SteamConfig);

    expect($check->requiredVersion)->toBe(10828683);
});
