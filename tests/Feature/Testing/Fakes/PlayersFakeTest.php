<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\BadgeFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\CommunityBadgeQuestFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\OwnedGameFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\PlayerBadgesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGameFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGamesFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\PlayersFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Request;

covers(PlayersFake::class);

// Envelopes

test('counts the games it wraps', function (): void {
    $answer = answerFrom(
        new SteamFake()->players()->ownedGames(OwnedGameFactory::new()->appId(381210), OwnedGameFactory::new()->appId(730)),
        new GetOwnedGamesRequest(steamId()),
    );

    expect($answer->body())->toBe(json_encode([
        'response' => [
            'game_count' => 2,
            'games' => [
                OwnedGameFactory::new()->appId(381210)->toArray(),
                OwnedGameFactory::new()->appId(730)->toArray(),
            ],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('nests recently played games under the response key', function (): void {
    $answer = answerFrom(
        new SteamFake()->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()->games(
            RecentlyPlayedGameFactory::new()->appId(381210),
            RecentlyPlayedGameFactory::new()->appId(730),
        )),
        new GetRecentlyPlayedGamesRequest(steamId()),
    );

    expect($answer->body())->toBe(json_encode([
        'response' => [
            'total_count' => 2,
            'games' => [
                RecentlyPlayedGameFactory::new()->appId(381210)->toArray(),
                RecentlyPlayedGameFactory::new()->appId(730)->toArray(),
            ],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('nests badges under the response key', function (): void {
    $answer = answerFrom(new SteamFake()->players()->badges(PlayerBadgesFactory::new()), new GetBadgesRequest(steamId()));

    expect($answer->body())->toBe(json_encode([
        'response' => PlayerBadgesFactory::new()->toArray(),
    ], JSON_THROW_ON_ERROR));
});

test('nests community badge quests under the response key', function (): void {
    $answer = answerFrom(
        new SteamFake()->players()->communityBadgeProgress(
            CommunityBadgeQuestFactory::new(),
            CommunityBadgeQuestFactory::new()->questId(202)->incomplete(),
        ),
        new GetCommunityBadgeProgressRequest(steamId()),
    );

    expect($answer->body())->toBe(json_encode([
        'response' => [
            'quests' => [
                CommunityBadgeQuestFactory::new()->toArray(),
                CommunityBadgeQuestFactory::new()->questId(202)->incomplete()->toArray(),
            ],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('sends the body steam was captured sending', function (Closure $fake, Request $request, string $fixture): void {
    $answer = answerFrom($fake(new SteamFake), $request);

    expect([$answer->status(), $answer->body()])->toBe(captured($fixture));
})->with([
    'ownedGamesNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->ownedGamesNotPublic(),
        new GetOwnedGamesRequest(steamId()),
        'IPlayerService/GetOwnedGames/private',
    ],
    'recentlyPlayedGamesNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGamesNotPublic(),
        new GetRecentlyPlayedGamesRequest(steamId()),
        'IPlayerService/GetRecentlyPlayedGames/private',
    ],
    'steamLevelNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->steamLevelNotPublic(),
        new GetSteamLevelRequest(steamId()),
        'IPlayerService/GetSteamLevel/private',
    ],
    'badgesNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->badgesNotPublic(),
        new GetBadgesRequest(steamId()),
        'IPlayerService/GetBadges/private',
    ],
    'communityBadgeProgressNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->communityBadgeProgressNotPublic(),
        new GetCommunityBadgeProgressRequest(steamId()),
        'IPlayerService/GetCommunityBadgeProgress/private',
    ],
    'ownedGames() of no games' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->ownedGames(),
        new GetOwnedGamesRequest(steamId()),
        'IPlayerService/GetOwnedGames/not_owned',
    ],
    'recentlyPlayedGames() of nothing played' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()->nothingPlayed()),
        new GetRecentlyPlayedGamesRequest(steamId()),
        'IPlayerService/GetRecentlyPlayedGames/empty',
    ],
    'steamLevel()' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->steamLevel(56),
        new GetSteamLevelRequest(steamId()),
        'IPlayerService/GetSteamLevel/default',
    ],
    'steamLevel() of zero' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->steamLevel(0),
        new GetSteamLevelRequest(steamId()),
        'IPlayerService/GetSteamLevel/zero',
    ],
]);

// Round trips through the connector

test('fakes every players endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->players()->ownedGames(OwnedGameFactory::new(), OwnedGameFactory::new()->withAppInfo())
        ->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()->games(
            RecentlyPlayedGameFactory::new()->name('Counter-Strike 2'),
        ))
        ->players()->steamLevel(42)
        ->players()->badges(PlayerBadgesFactory::new()->badges(BadgeFactory::new()->communityItem()))
        ->players()->communityBadgeProgress(
            CommunityBadgeQuestFactory::new(),
            CommunityBadgeQuestFactory::new()->questId(202)->incomplete(),
        );
    $connector = fakedConnector($fake);

    $ownedGames = $connector->players()->ownedGames(steamId());
    $badges = $connector->players()->badges(steamId());
    $quests = $connector->players()->communityBadgeProgress(steamId());

    expect($ownedGames)->toHaveCount(2)
        ->and($ownedGames[1]->name)->toBe('Dead by Daylight')
        ->and($connector->players()->recentlyPlayedGames(steamId())->games[0]->name)->toBe('Counter-Strike 2')
        ->and($connector->players()->steamLevel(steamId()))->toBe(42)
        ->and($badges->playerLevel)->toBe(12)
        ->and($badges->badges[0]->communityItemId)->toBe('2101234567890123456')
        ->and($quests)->toHaveCount(2)
        ->and($quests[1]->questId)->toBe(202)
        ->and($quests[1]->completed)->toBeFalse();

    $fake->assertSentCount(5);
});

// Failures — these pin the contract with the SDK's exception mapping

test('raises what the sdk raises for a named failure', function (Closure $fail, Closure $call, string $exception): void {
    $fake = new SteamFake;
    $connector = fakedConnector($fake);

    expect($fail($fake))->toBe($fake)
        ->and(fn (): mixed => $call($connector))->toThrow($exception);
})->with([
    'owned games not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->ownedGamesNotPublic(),
        fn (SteamConnector $connector): array => $connector->players()->ownedGames(steamId()),
        ProfileNotPublicException::class,
    ],
    'recently played games not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGamesNotPublic(),
        fn (SteamConnector $connector): RecentlyPlayedGames => $connector->players()->recentlyPlayedGames(steamId()),
        ProfileNotPublicException::class,
    ],
    'steam level not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->steamLevelNotPublic(),
        fn (SteamConnector $connector): int => $connector->players()->steamLevel(steamId()),
        ProfileNotPublicException::class,
    ],
    'badges not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->badgesNotPublic(),
        fn (SteamConnector $connector): PlayerBadges => $connector->players()->badges(steamId()),
        ProfileNotPublicException::class,
    ],
    'community badge progress not public' => [
        fn (SteamFake $fake): SteamFake => $fake->players()->communityBadgeProgressNotPublic(),
        fn (SteamConnector $connector): array => $connector->players()->communityBadgeProgress(steamId()),
        ProfileNotPublicException::class,
    ],
]);
