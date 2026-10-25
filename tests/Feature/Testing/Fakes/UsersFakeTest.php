<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\FriendFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerBanFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\UserGroupFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\UsersFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Request;

covers(UsersFake::class);

dataset('player lists', [
    'summaries' => [
        static fn (SteamFake $fake, SteamId ...$steamIds): SteamFake => $fake->users()->summaries(...array_map(
            static fn (SteamId $steamId): PlayerSummaryFactory => PlayerSummaryFactory::new()->steamId($steamId),
            $steamIds,
        )),
        static fn (SteamConnector $connector, SteamId ...$steamIds): array => array_map(
            static fn (PlayerSummary $summary): string => $summary->steamId->value,
            $connector->users()->summaries($steamIds),
        ),
    ],
    'bans' => [
        static fn (SteamFake $fake, SteamId ...$steamIds): SteamFake => $fake->users()->bans(...array_map(
            static fn (SteamId $steamId): PlayerBanFactory => PlayerBanFactory::new()->steamId($steamId),
            $steamIds,
        )),
        static fn (SteamConnector $connector, SteamId ...$steamIds): array => array_map(
            static fn (PlayerBan $ban): string => $ban->steamId->value,
            $connector->users()->bans($steamIds),
        ),
    ],
]);

// Envelopes

test('nests player summaries under the response key', function (): void {
    $answer = answerFrom(
        new SteamFake()->users()->summaries(PlayerSummaryFactory::new()->personaName('Gabe'), PlayerSummaryFactory::new()->personaName('Newell')),
        new GetPlayerSummariesRequest([steamId()]),
    );

    expect($answer->body())->toBe(json_encode([
        'response' => [
            'players' => [
                PlayerSummaryFactory::new()->personaName('Gabe')->toArray(),
                PlayerSummaryFactory::new()->personaName('Newell')->toArray(),
            ],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('puts ban records at the top level, without the response wrapper', function (): void {
    $answer = answerFrom(new SteamFake()->users()->bans(PlayerBanFactory::new()->vacBanned(2)), new GetPlayerBansRequest([steamId()]));

    expect($answer->body())->toBe(json_encode([
        'players' => [PlayerBanFactory::new()->vacBanned(2)->toArray()],
    ], JSON_THROW_ON_ERROR));
});

test('lists the players a request asks for however far down the fake they sit', function (): void {
    [$first, $second] = makeSteamIds(2);

    $answer = answerFrom(
        new SteamFake()->users()->summaries(PlayerSummaryFactory::new()->steamId($first), PlayerSummaryFactory::new()->steamId($second)),
        new GetPlayerSummariesRequest([$second]),
    );

    expect($answer->body())->toBe(json_encode([
        'response' => ['players' => [PlayerSummaryFactory::new()->steamId($second)->toArray()]],
    ], JSON_THROW_ON_ERROR));
});

test('nests friends under the friendslist key', function (): void {
    $answer = answerFrom(new SteamFake()->users()->friends(FriendFactory::new()), new GetFriendListRequest(steamId()));

    expect($answer->body())->toBe(json_encode([
        'friendslist' => ['friends' => [FriendFactory::new()->toArray()]],
    ], JSON_THROW_ON_ERROR));
});

test('flags the group list as successful', function (): void {
    $answer = answerFrom(new SteamFake()->users()->groups(UserGroupFactory::new()), new GetUserGroupListRequest(steamId()));

    expect($answer->body())->toBe(json_encode([
        'response' => [
            'success' => true,
            'groups' => [UserGroupFactory::new()->toArray()],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('sends the body steam was captured sending', function (Closure $fake, Request $request, string $fixture): void {
    $answer = answerFrom($fake(new SteamFake), $request);

    expect([$answer->status(), $answer->body()])->toBe(captured($fixture));
})->with([
    'friendsNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->friendsNotPublic(),
        new GetFriendListRequest(steamId()),
        'ISteamUser/GetFriendList/private',
    ],
    'groupsNotPublic()' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->groupsNotPublic(),
        new GetUserGroupListRequest(steamId()),
        'ISteamUser/GetUserGroupList/private',
    ],
    'vanityUrlNotFound()' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->vanityUrlNotFound(),
        new ResolveVanityUrlRequest('nobody'),
        'ISteamUser/ResolveVanityUrl/not_found',
    ],
    'bans() of no players' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->bans(),
        new GetPlayerBansRequest([steamId()]),
        'ISteamUser/GetPlayerBans/empty',
    ],
    'friends() of no friends' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->friends(),
        new GetFriendListRequest(steamId()),
        'ISteamUser/GetFriendList/empty',
    ],
    'groups() of no groups' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->groups(),
        new GetUserGroupListRequest(steamId()),
        'ISteamUser/GetUserGroupList/empty',
    ],
    'resolveVanityUrl()' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->resolveVanityUrl(steamId()),
        new ResolveVanityUrlRequest('gabelogannewell'),
        'ISteamUser/ResolveVanityUrl/success',
    ],
]);

// Round trips through the connector

test('fakes every users endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->users()->summaries(PlayerSummaryFactory::new(), PlayerSummaryFactory::new()->personaName('Gabe'))
        ->users()->bans(PlayerBanFactory::new(), PlayerBanFactory::new()->vacBanned(2))
        ->users()->friends(FriendFactory::new(), FriendFactory::new())
        ->users()->groups(UserGroupFactory::new(), UserGroupFactory::new()->gid('25358940'))
        ->users()->resolveVanityUrl(steamId());
    $connector = fakedConnector($fake);

    $summaries = $connector->users()->summaries([steamId()]);
    $bans = $connector->users()->bans([steamId()]);
    $groups = $connector->users()->groups(steamId());

    expect($summaries)->toHaveCount(2)
        ->and($summaries[1]->personaName)->toBe('Gabe')
        ->and($bans)->toHaveCount(2)
        ->and($bans[1]->numberOfVacBans)->toBe(2)
        ->and($connector->users()->friends(steamId()))->toHaveCount(2)
        ->and($groups)->toHaveCount(2)
        ->and($groups[1]->gid)->toBe('25358940')
        ->and($connector->users()->resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000');

    $fake->assertSentCount(5);
});

test('answers each chunk past 100 Steam IDs with the players it asks for', function (Closure $fake, Closure $call): void {
    $steamIds = makeSteamIds(150);
    $mock = $fake(new SteamFake, ...$steamIds);

    expect($call(fakedConnector($mock), ...$steamIds))
        ->toEqualCanonicalizing(array_map(static fn (SteamId $steamId): string => $steamId->value, $steamIds));

    $mock->assertSentCount(2);
})->with('player lists');

test('answers a chunk asking for none of the faked players without the ones another chunk got', function (Closure $fake, Closure $call): void {
    $steamIds = makeSteamIds(150);
    $mock = $fake(new SteamFake, $steamIds[3], $steamIds[50]);

    expect($call(fakedConnector($mock), ...$steamIds))->toBe([$steamIds[3]->value, $steamIds[50]->value]);

    $mock->assertSentCount(2);
})->with('player lists');

test('answers a chunk asking for the faked players another chunk got unasked with none', function (Closure $fake, Closure $call): void {
    $steamIds = makeSteamIds(150);
    $mock = $fake(new SteamFake, $steamIds[120], $steamIds[130]);

    expect($call(fakedConnector($mock), ...$steamIds))->toBe([$steamIds[120]->value, $steamIds[130]->value]);

    $mock->assertSentCount(2);
})->with('player lists');

test('answers a second call asking for none of the faked players with none', function (Closure $fake, Closure $call): void {
    [$first, $second, $unknown] = makeSteamIds(3);
    $connector = fakedConnector($fake(new SteamFake, $first, $second));

    expect($call($connector, $unknown))->toBe([$first->value, $second->value])
        ->and($call($connector, $unknown))->toBe([]);
})->with('player lists');

test('answers a second call asking for none with the faked players no call has had', function (Closure $fake, Closure $call): void {
    [$asked, $other, $unknown] = makeSteamIds(3);
    $connector = fakedConnector($fake(new SteamFake, $asked, $other));

    expect($call($connector, $asked))->toBe([$asked->value])
        ->and($call($connector, $unknown))->toBe([$other->value]);
})->with('player lists');

test('answers every call with the faked players it asks for', function (Closure $fake, Closure $call): void {
    [$asked, $other] = makeSteamIds(2);
    $connector = fakedConnector($fake(new SteamFake, $asked, $other));

    expect($call($connector, $asked))->toBe([$asked->value])
        ->and($call($connector, $asked))->toBe([$asked->value]);
})->with('player lists');

test('answers only the faked players a request asks for', function (Closure $fake, Closure $call): void {
    [$asked, $other, $unknown] = makeSteamIds(3);

    expect($call(fakedConnector($fake(new SteamFake, $asked, $other)), $asked, $unknown))->toBe([$asked->value]);
})->with('player lists');

test('answers with every faked player when a request asks for none of them', function (Closure $fake, Closure $call): void {
    [$first, $second, $unknown] = makeSteamIds(3);

    expect($call(fakedConnector($fake(new SteamFake, $first, $second)), $unknown))->toBe([$first->value, $second->value]);
})->with('player lists');

test('answers with no players when none are faked', function (Closure $fake, Closure $call): void {
    expect($call(fakedConnector($fake(new SteamFake)), steamId()))->toBe([]);
})->with('player lists');

// Failures — these pin the contract with the SDK's exception mapping

test('raises what the sdk raises for a named failure', function (Closure $fail, Closure $call, string $exception): void {
    $fake = new SteamFake;
    $connector = fakedConnector($fake);

    expect($fail($fake))->toBe($fake)
        ->and(fn (): mixed => $call($connector))->toThrow($exception);
})->with([
    'friends not public' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->friendsNotPublic(),
        fn (SteamConnector $connector): array => $connector->users()->friends(steamId()),
        ProfileNotPublicException::class,
    ],
    'groups not public' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->groupsNotPublic(),
        fn (SteamConnector $connector): array => $connector->users()->groups(steamId()),
        ProfileNotPublicException::class,
    ],
    'vanity url not found' => [
        fn (SteamFake $fake): SteamFake => $fake->users()->vanityUrlNotFound(),
        fn (SteamConnector $connector): SteamId => $connector->users()->resolveVanityUrl('nobody'),
        SteamUserNotFoundException::class,
    ],
]);
