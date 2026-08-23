<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetSteamLevelRequest::class, ProfileNotPublicException::class]);

function steamLevelSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function sendSteamLevelFixture(string $fixture): int
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetSteamLevelRequest::class => MockResponse::fixture(
            sprintf('IPlayerService/GetSteamLevel/%s', $fixture),
        ),
    ]));

    /** @var int $level */
    $level = $connector->send(new GetSteamLevelRequest(steamLevelSteamId()))->dto();

    return $level;
}

test('endpoint targets GetSteamLevel v1', function (): void {
    $request = new GetSteamLevelRequest(steamLevelSteamId());

    expect($request->resolveEndpoint())->toBe('/IPlayerService/GetSteamLevel/v1/');
});

test('query carries steamid parameter', function (): void {
    $request = new GetSteamLevelRequest(steamLevelSteamId());

    expect($request->query()->all())->toBe(['steamid' => '76561198000000000']);
});

test('fixture response yields the level as an int', function (): void {
    expect(sendSteamLevelFixture('default'))->toBe(56);
});

test('level zero comes back as zero instead of throwing', function (): void {
    expect(sendSteamLevelFixture('zero'))->toBe(0);
});

test('withheld profile throws ProfileNotPublicException', function (): void {
    sendSteamLevelFixture('private');
})->throws(
    ProfileNotPublicException::class,
    'Steam profile 76561198000000000 is not public.',
);
