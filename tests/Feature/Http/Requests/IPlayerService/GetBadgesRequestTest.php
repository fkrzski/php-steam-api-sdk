<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\Badge;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([
    GetBadgesRequest::class,
    PlayerBadges::class,
    Badge::class,
    ProfileNotPublicException::class,
]);

function badgesSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function sendBadgesFixture(string $fixture): PlayerBadges
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetBadgesRequest::class => MockResponse::fixture(
            sprintf('IPlayerService/GetBadges/%s', $fixture),
        ),
    ]));

    /** @var PlayerBadges $dto */
    $dto = $connector->send(new GetBadgesRequest(badgesSteamId()))->dto();

    return $dto;
}

test('endpoint targets GetBadges v1', function (): void {
    $request = new GetBadgesRequest(badgesSteamId());

    expect($request->resolveEndpoint())->toBe('/IPlayerService/GetBadges/v1/');
});

test('query carries steamid parameter', function (): void {
    $request = new GetBadgesRequest(badgesSteamId());

    expect($request->query()->all())->toBe(['steamid' => '76561198000000000']);
});

test('fixture response parses into Badge DTOs alongside the player progress', function (): void {
    $dto = sendBadgesFixture('default');

    expect($dto)->toBeInstanceOf(PlayerBadges::class)
        ->and($dto->badges)->toHaveCount(3)
        ->and($dto->badges[0])->toBeInstanceOf(Badge::class)
        ->and($dto->badges[0]->badgeId)->toBe(69)
        ->and($dto->badges[0]->level)->toBe(1)
        ->and($dto->badges[0]->completedAt->getTimestamp())->toBe(1787222588)
        ->and($dto->badges[0]->xp)->toBe(50)
        ->and($dto->badges[0]->scarcity)->toBe(30146528)
        ->and($dto->playerXp)->toBe(18668)
        ->and($dto->playerLevel)->toBe(56)
        ->and($dto->xpNeededToLevelUp)->toBe(532)
        ->and($dto->xpNeededForCurrentLevel)->toBe(18600);
});

test('trading card badge exposes the app it belongs to', function (): void {
    $badge = sendBadgesFixture('default')->badges[2];

    expect($badge->appId)->toBe(730)
        ->and($badge->communityItemId)->toBe('4779743956')
        ->and($badge->borderColor)->toBe(0);
});

test('badge outside a game leaves the trading card fields null', function (): void {
    $badge = sendBadgesFixture('default')->badges[0];

    expect($badge->appId)->toBeNull()
        ->and($badge->communityItemId)->toBeNull()
        ->and($badge->borderColor)->toBeNull();
});

test('badge worth no experience keeps zero instead of turning into null', function (): void {
    $badge = sendBadgesFixture('default')->badges[1];

    expect($badge->xp)->toBe(0);
});

test('account without badges comes back empty instead of throwing', function (): void {
    $dto = sendBadgesFixture('zero');

    expect($dto->badges)->toBeEmpty()
        ->and($dto->playerXp)->toBe(0)
        ->and($dto->playerLevel)->toBe(0)
        ->and($dto->xpNeededToLevelUp)->toBe(100)
        ->and($dto->xpNeededForCurrentLevel)->toBe(0);
});

test('withheld profile throws ProfileNotPublicException', function (): void {
    sendBadgesFixture('private');
})->throws(
    ProfileNotPublicException::class,
    'Steam profile 76561198000000000 is not public.',
);
