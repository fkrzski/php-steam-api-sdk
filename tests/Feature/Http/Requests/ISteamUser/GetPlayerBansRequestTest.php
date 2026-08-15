<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Enums\EconomyBan;
use Fkrzski\SteamApiSdk\Exceptions\TooManySteamIdsException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetPlayerBansRequest::class, PlayerBan::class]);

function bansConnector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

/**
 * @return list<PlayerBan>
 */
function sendBansFixture(string $fixture, int $steamIdCount = 1): array
{
    $mock = new MockClient([
        GetPlayerBansRequest::class => MockResponse::fixture('ISteamUser/GetPlayerBans/'.$fixture),
    ]);

    $connector = bansConnector();
    $connector->withMockClient($mock);

    /** @var list<PlayerBan> $dtos */
    $dtos = $connector->send(new GetPlayerBansRequest(makeSteamIds($steamIdCount)))->dto();

    return $dtos;
}

test('endpoint targets GetPlayerBans v1', function (): void {
    $request = new GetPlayerBansRequest(makeSteamIds(1));

    expect($request->resolveEndpoint())->toBe('/ISteamUser/GetPlayerBans/v1/');
});

test('query joins steamids with comma', function (): void {
    $request = new GetPlayerBansRequest(makeSteamIds(2));

    expect($request->query()->all())->toBe([
        'steamids' => '76561198000000000,76561198000000001',
    ]);
});

test('empty steamid list rejected', function (): void {
    new GetPlayerBansRequest([]);
})->throws(InvalidArgumentException::class, 'GetPlayerBansRequest requires at least one SteamId.');

test('more than 100 steamids rejected', function (): void {
    new GetPlayerBansRequest(makeSteamIds(101));
})->throws(TooManySteamIdsException::class, 'GetPlayerBans accepts up to 100 SteamIDs, 101 given.');

test('exactly 100 steamids accepted', function (): void {
    $request = new GetPlayerBansRequest(makeSteamIds(100));

    expect($request->steamIds)->toHaveCount(100);
});

test('fixture response parses top-level players into PlayerBan DTOs', function (): void {
    $dtos = sendBansFixture('default', 2);

    expect($dtos)->toHaveCount(2)
        ->and($dtos[0])->toBeInstanceOf(PlayerBan::class)
        ->and($dtos[0]->steamId)->toBeInstanceOf(SteamId::class)
        ->and($dtos[0]->steamId->value)->toBe('76561198000000000')
        ->and($dtos[0]->isCommunityBanned)->toBeFalse()
        ->and($dtos[0]->isVacBanned)->toBeFalse()
        ->and($dtos[0]->numberOfVacBans)->toBe(0)
        ->and($dtos[0]->numberOfGameBans)->toBe(0)
        ->and($dtos[0]->daysSinceLastBan)->toBe(0)
        ->and($dtos[0]->economyBan)->toBe(EconomyBan::None)
        ->and($dtos[1]->steamId->value)->toBe('76561198000000001')
        ->and($dtos[1]->isCommunityBanned)->toBeFalse()
        ->and($dtos[1]->isVacBanned)->toBeTrue()
        ->and($dtos[1]->numberOfVacBans)->toBe(1)
        ->and($dtos[1]->numberOfGameBans)->toBe(0)
        ->and($dtos[1]->daysSinceLastBan)->toBe(3216)
        ->and($dtos[1]->economyBan)->toBe(EconomyBan::None);
});

test('community banned player parses every ban counter', function (): void {
    $dtos = sendBansFixture('community_banned');

    expect($dtos)->toHaveCount(1)
        ->and($dtos[0]->steamId->value)->toBe('76561198000000002')
        ->and($dtos[0]->isCommunityBanned)->toBeTrue()
        ->and($dtos[0]->isVacBanned)->toBeTrue()
        ->and($dtos[0]->numberOfVacBans)->toBe(63)
        ->and($dtos[0]->numberOfGameBans)->toBe(85)
        ->and($dtos[0]->daysSinceLastBan)->toBe(209)
        ->and($dtos[0]->economyBan)->toBe(EconomyBan::None);
});

test('economy banned player maps EconomyBan to the Banned case', function (): void {
    $dtos = sendBansFixture('economy_banned');

    expect($dtos)->toHaveCount(1)
        ->and($dtos[0]->steamId->value)->toBe('76561198000000003')
        ->and($dtos[0]->isCommunityBanned)->toBeTrue()
        ->and($dtos[0]->isVacBanned)->toBeFalse()
        ->and($dtos[0]->economyBan)->toBe(EconomyBan::Banned);
});

test('empty players list yields no DTOs', function (): void {
    expect(sendBansFixture('empty'))->toBe([]);
});

test('response without a players key yields no DTOs', function (): void {
    $mock = new MockClient([
        GetPlayerBansRequest::class => MockResponse::make(['unexpected' => true]),
    ]);

    $connector = bansConnector();
    $connector->withMockClient($mock);

    expect($connector->send(new GetPlayerBansRequest(makeSteamIds(1)))->dto())->toBe([]);
});
