<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;
use Fkrzski\SteamApiSdk\Dto\OwnedGame;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(PlayersResource::class);

function playersResourceSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function playersResourceMock(string $requestClass, string $fixture): MockClient
{
    return new MockClient([$requestClass => MockResponse::fixture($fixture)]);
}

function playersResource(MockClient $mockClient): PlayersResource
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient($mockClient);

    return $connector->players();
}

test('ownedGames sends GetOwnedGames and returns OwnedGame DTOs', function (): void {
    $mockClient = playersResourceMock(GetOwnedGamesRequest::class, 'IPlayerService/GetOwnedGames/default');

    $games = playersResource($mockClient)->ownedGames(playersResourceSteamId());

    expect($games)->toHaveCount(1)
        ->and($games[0])->toBeInstanceOf(OwnedGame::class)
        ->and($games[0]->appId)->toBe(381210)
        ->and($mockClient->getLastRequest())->toBeInstanceOf(GetOwnedGamesRequest::class);
});

test('ownedGames omits every optional parameter by default', function (): void {
    $mockClient = playersResourceMock(GetOwnedGamesRequest::class, 'IPlayerService/GetOwnedGames/default');

    playersResource($mockClient)->ownedGames(playersResourceSteamId());

    expect($mockClient->getLastRequest()?->query()->all())->toBe(['steamid' => '76561198000000000']);
});

test('ownedGames forwards every optional parameter', function (): void {
    $mockClient = playersResourceMock(GetOwnedGamesRequest::class, 'IPlayerService/GetOwnedGames/default');

    playersResource($mockClient)->ownedGames(playersResourceSteamId(), [381210], true, true);

    expect($mockClient->getLastRequest()?->query()->all())->toBe([
        'steamid' => '76561198000000000',
        'appids_filter' => [381210],
        'include_appinfo' => 1,
        'include_played_free_games' => 1,
    ]);
});

test('recentlyPlayedGames sends GetRecentlyPlayedGames and returns the DTO', function (): void {
    $mockClient = playersResourceMock(
        GetRecentlyPlayedGamesRequest::class,
        'IPlayerService/GetRecentlyPlayedGames/default',
    );

    $recent = playersResource($mockClient)->recentlyPlayedGames(playersResourceSteamId());

    expect($recent)->toBeInstanceOf(RecentlyPlayedGames::class)
        ->and($recent->totalCount)->toBe(3)
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['steamid' => '76561198000000000']);
});

test('recentlyPlayedGames forwards the count limit', function (): void {
    $mockClient = playersResourceMock(
        GetRecentlyPlayedGamesRequest::class,
        'IPlayerService/GetRecentlyPlayedGames/limited',
    );

    playersResource($mockClient)->recentlyPlayedGames(playersResourceSteamId(), 2);

    expect($mockClient->getLastRequest()?->query()->all())->toBe([
        'steamid' => '76561198000000000',
        'count' => 2,
    ]);
});

test('steamLevel sends GetSteamLevel and returns a plain int', function (): void {
    $mockClient = playersResourceMock(GetSteamLevelRequest::class, 'IPlayerService/GetSteamLevel/default');

    $level = playersResource($mockClient)->steamLevel(playersResourceSteamId());

    expect($level)->toBe(56)
        ->and($mockClient->getLastRequest())->toBeInstanceOf(GetSteamLevelRequest::class);
});

test('badges sends GetBadges and returns the DTO', function (): void {
    $mockClient = playersResourceMock(GetBadgesRequest::class, 'IPlayerService/GetBadges/default');

    $badges = playersResource($mockClient)->badges(playersResourceSteamId());

    expect($badges)->toBeInstanceOf(PlayerBadges::class)
        ->and($badges->playerLevel)->toBe(56)
        ->and($badges->badges)->toHaveCount(3);
});

test('communityBadgeProgress sends GetCommunityBadgeProgress and returns quest DTOs', function (): void {
    $mockClient = playersResourceMock(
        GetCommunityBadgeProgressRequest::class,
        'IPlayerService/GetCommunityBadgeProgress/default',
    );

    $quests = playersResource($mockClient)->communityBadgeProgress(playersResourceSteamId());

    expect($quests)->toHaveCount(3)
        ->and($quests[0])->toBeInstanceOf(CommunityBadgeQuest::class)
        ->and($quests[0]->questId)->toBe(115);
});
