<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([
    GetCommunityBadgeProgressRequest::class,
    CommunityBadgeQuest::class,
    ProfileNotPublicException::class,
]);

function communityBadgeSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

/**
 * @return list<CommunityBadgeQuest>
 */
function sendCommunityBadgeFixture(string $fixture): array
{
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetCommunityBadgeProgressRequest::class => MockResponse::fixture(
            sprintf('IPlayerService/GetCommunityBadgeProgress/%s', $fixture),
        ),
    ]));

    /** @var list<CommunityBadgeQuest> $dtos */
    $dtos = $connector->send(new GetCommunityBadgeProgressRequest(communityBadgeSteamId()))->dto();

    return $dtos;
}

test('endpoint targets GetCommunityBadgeProgress v1', function (): void {
    $request = new GetCommunityBadgeProgressRequest(communityBadgeSteamId());

    expect($request->resolveEndpoint())->toBe('/IPlayerService/GetCommunityBadgeProgress/v1/');
});

test('query carries steamid parameter', function (): void {
    $request = new GetCommunityBadgeProgressRequest(communityBadgeSteamId());

    expect($request->query()->all())->toBe(['steamid' => '76561198000000000']);
});

test('fixture response parses into CommunityBadgeQuest DTOs', function (): void {
    $quests = sendCommunityBadgeFixture('default');

    expect($quests)->toHaveCount(3)
        ->and($quests[0])->toBeInstanceOf(CommunityBadgeQuest::class)
        ->and($quests[0]->questId)->toBe(115)
        ->and($quests[0]->completed)->toBeTrue()
        ->and($quests[2]->questId)->toBe(134);
});

test('withheld profile throws ProfileNotPublicException', function (): void {
    sendCommunityBadgeFixture('private');
})->throws(
    ProfileNotPublicException::class,
    'Steam returned no data for profile 76561198000000000: it is not public, or it does not exist.',
);
