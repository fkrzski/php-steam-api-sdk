<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Enums\CommentPermission;
use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;
use Fkrzski\SteamApiSdk\Enums\PersonaState;
use Fkrzski\SteamApiSdk\Exceptions\TooManySteamIdsException;
use Fkrzski\SteamApiSdk\Http\Requests\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

function summariesConnector(): SteamConnector
{
    return new SteamConnector(new SteamConfig('test-key'));
}

/**
 * @return list<SteamId>
 */
function makeSteamIds(int $count): array
{
    $base = 76561198000000000;

    return array_map(
        static fn (int $offset): SteamId => SteamId::fromSteamId64((string) ($base + $offset)),
        range(0, $count - 1),
    );
}

test('endpoint targets GetPlayerSummaries v2', function (): void {
    $request = new GetPlayerSummariesRequest(makeSteamIds(1));

    expect($request->resolveEndpoint())->toBe('/ISteamUser/GetPlayerSummaries/v2/');
});

test('query joins steamids with comma', function (): void {
    $request = new GetPlayerSummariesRequest(makeSteamIds(2));

    expect($request->query()->all())->toBe([
        'steamids' => '76561198000000000,76561198000000001',
    ]);
});

test('empty steamid list rejected', function (): void {
    new GetPlayerSummariesRequest([]);
})->throws(InvalidArgumentException::class, 'GetPlayerSummariesRequest requires at least one SteamId.');

test('more than 100 steamids rejected', function (): void {
    new GetPlayerSummariesRequest(makeSteamIds(101));
})->throws(TooManySteamIdsException::class, 'GetPlayerSummaries accepts up to 100 SteamIDs, 101 given.');

test('exactly 100 steamids accepted', function (): void {
    $request = new GetPlayerSummariesRequest(makeSteamIds(100));

    expect($request->steamIds)->toHaveCount(100);
});

test('fixture response parses into PlayerSummary DTOs', function (): void {
    $mock = new MockClient([
        GetPlayerSummariesRequest::class => MockResponse::fixture('get_player_summaries'),
    ]);

    $connector = summariesConnector();
    $connector->withMockClient($mock);

    /** @var list<PlayerSummary> $dtos */
    $dtos = $connector->send(new GetPlayerSummariesRequest(makeSteamIds(2)))->dto();

    expect($dtos)->toHaveCount(2)
        ->and($dtos[0])->toBeInstanceOf(PlayerSummary::class)
        ->and($dtos[0]->steamId)->toBeInstanceOf(SteamId::class)
        ->and($dtos[0]->steamId->value)->toBe('76561198000000000')
        ->and($dtos[0]->personaName)->toBe('tester-one')
        ->and($dtos[0]->communityVisibility)->toBe(CommunityVisibility::Visible)
        ->and($dtos[0]->hasCommunityProfile)->toBeTrue()
        ->and($dtos[0]->commentPermission)->toBe(CommentPermission::Everyone)
        ->and($dtos[0]->personaState)->toBe(PersonaState::Online)
        ->and($dtos[0]->realName)->toBeNull()
        ->and($dtos[0]->lastLogOff)->toBeNull()
        ->and($dtos[0]->gameExtraInfo)->toBe('Dead by Daylight')
        ->and($dtos[0]->gameId)->toBe('381210')
        ->and($dtos[0]->gameServerIp)->toBeNull()
        ->and($dtos[0]->countryCode)->toBeNull()
        ->and($dtos[0]->stateCode)->toBeNull()
        ->and($dtos[0]->cityId)->toBeNull()
        ->and($dtos[0]->timeCreated->getTimestamp())->toBe(1407003640)
        ->and($dtos[1]->steamId->value)->toBe('76561198000000001')
        ->and($dtos[1]->commentPermission)->toBe(CommentPermission::Nobody)
        ->and($dtos[1]->personaState)->toBe(PersonaState::Offline)
        ->and($dtos[1]->realName)->toBe('Test User')
        ->and($dtos[1]->lastLogOff)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($dtos[1]->lastLogOff?->getTimestamp())->toBe(1768203860)
        ->and($dtos[1]->gameExtraInfo)->toBeNull()
        ->and($dtos[1]->gameId)->toBeNull()
        ->and($dtos[1]->gameServerIp)->toBe('1.2.3.4:27015')
        ->and($dtos[1]->countryCode)->toBe('PL')
        ->and($dtos[1]->stateCode)->toBe('MZ')
        ->and($dtos[1]->cityId)->toBe(12345)
        ->and($dtos[1]->primaryClanId)->toBe('103582791470999338');
});
