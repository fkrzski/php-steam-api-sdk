<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGame;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([
    GetRecentlyPlayedGamesRequest::class,
    RecentlyPlayedGames::class,
    RecentlyPlayedGame::class,
    ProfileNotPublicException::class,
]);

function recentlyPlayedSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function sendRecentlyPlayedFixture(string $fixture, ?int $count = null): RecentlyPlayedGames
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetRecentlyPlayedGamesRequest::class => MockResponse::fixture(
            sprintf('IPlayerService/GetRecentlyPlayedGames/%s', $fixture),
        ),
    ]));

    /** @var RecentlyPlayedGames $dto */
    $dto = $connector->send(new GetRecentlyPlayedGamesRequest(recentlyPlayedSteamId(), $count))->dto();

    return $dto;
}

test('endpoint targets GetRecentlyPlayedGames v1', function (): void {
    $request = new GetRecentlyPlayedGamesRequest(recentlyPlayedSteamId());

    expect($request->resolveEndpoint())->toBe('/IPlayerService/GetRecentlyPlayedGames/v1/');
});

test('query includes steamid', function (): void {
    $request = new GetRecentlyPlayedGamesRequest(recentlyPlayedSteamId());

    expect($request->query()->all())->toMatchArray(['steamid' => '76561198000000000']);
});

test('query includes count when provided', function (): void {
    $request = new GetRecentlyPlayedGamesRequest(recentlyPlayedSteamId(), 2);

    expect($request->query()->all())->toMatchArray(['count' => 2]);
});

test('query omits count by default', function (): void {
    $request = new GetRecentlyPlayedGamesRequest(recentlyPlayedSteamId());

    expect($request->query()->all())->not->toHaveKey('count');
});

test('fixture response parses into RecentlyPlayedGame DTOs', function (): void {
    $dto = sendRecentlyPlayedFixture('default');

    expect($dto)->toBeInstanceOf(RecentlyPlayedGames::class)
        ->and($dto->totalCount)->toBe(3)
        ->and($dto->games)->toHaveCount(3)
        ->and($dto->games[0])->toBeInstanceOf(RecentlyPlayedGame::class)
        ->and($dto->games[0]->appId)->toBe(220260)
        ->and($dto->games[0]->name)->toBe('Farming Simulator 2013: Titanium Edition')
        ->and($dto->games[0]->playtimeTwoWeeks)->toBe(1975)
        ->and($dto->games[0]->playtimeForever)->toBe(6583)
        ->and($dto->games[0]->imgIconUrl)->toBe('79a6827ff70f6e6ccfb7878e548011b6805c0143')
        ->and($dto->games[0]->playtimeWindowsForever)->toBe(6583)
        ->and($dto->games[0]->playtimeMacForever)->toBe(0)
        ->and($dto->games[0]->playtimeLinuxForever)->toBe(0)
        ->and($dto->games[0]->playtimeDeckForever)->toBe(0)
        ->and($dto->games[1]->playtimeLinuxForever)->toBe(89);
});

test('totalCount keeps the unlimited total when count truncates the list', function (): void {
    $dto = sendRecentlyPlayedFixture('limited', 2);

    expect($dto->games)->toHaveCount(2)
        ->and($dto->totalCount)->toBe(3);
});

test('nothing played in two weeks returns a zeroed total and no games', function (): void {
    $dto = sendRecentlyPlayedFixture('empty');

    expect($dto->totalCount)->toBe(0)
        ->and($dto->games)->toBeEmpty();
});

test('withheld profile throws ProfileNotPublicException naming both causes', function (): void {
    sendRecentlyPlayedFixture('private');
})->throws(
    ProfileNotPublicException::class,
    'Steam returned no data for profile 76561198000000000: it is not public, or it does not exist.',
);
