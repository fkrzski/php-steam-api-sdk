<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievements;
use Fkrzski\SteamApiSdk\Dto\PlayerBadges;
use Fkrzski\SteamApiSdk\Dto\RecentlyPlayedGames;
use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Dto\UserStats;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\BadgeFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\CommunityBadgeQuestFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\OwnedGameFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\PlayerBadgesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGameFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGamesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\AppVersionCheckFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\GameServerFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrConfigFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrPointOfPresenceFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\AppNewsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\FriendFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerBanFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\UserGroupFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GameSchemaFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GlobalAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiMethodFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiParameterFactory;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(SteamResponse::class);

function fakedId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function bodyOf(MockResponse $response): mixed
{
    return $response->body()->all();
}

// Envelopes

test('nests player summaries under the response key', function (): void {
    expect(bodyOf(SteamResponse::playerSummaries(
        PlayerSummaryFactory::new()->personaName('Gabe'),
        PlayerSummaryFactory::new()->personaName('Newell'),
    )))->toBe([
        'response' => [
            'players' => [
                PlayerSummaryFactory::new()->personaName('Gabe')->toArray(),
                PlayerSummaryFactory::new()->personaName('Newell')->toArray(),
            ],
        ],
    ]);
});

test('puts ban records at the top level, without the response wrapper', function (): void {
    expect(bodyOf(SteamResponse::playerBans(PlayerBanFactory::new()->vacBanned(2))))->toBe([
        'players' => [PlayerBanFactory::new()->vacBanned(2)->toArray()],
    ]);
});

test('nests friends under the friendslist key', function (): void {
    expect(bodyOf(SteamResponse::friendList(FriendFactory::new())))->toBe([
        'friendslist' => ['friends' => [FriendFactory::new()->toArray()]],
    ]);
});

test('flags the group list as successful', function (): void {
    expect(bodyOf(SteamResponse::userGroupList(UserGroupFactory::new())))->toBe([
        'response' => [
            'success' => true,
            'groups' => [UserGroupFactory::new()->toArray()],
        ],
    ]);
});

test('counts the games it wraps', function (): void {
    expect(bodyOf(SteamResponse::ownedGames(
        OwnedGameFactory::new()->appId(381210),
        OwnedGameFactory::new()->appId(730),
    )))->toBe([
        'response' => [
            'game_count' => 2,
            'games' => [
                OwnedGameFactory::new()->appId(381210)->toArray(),
                OwnedGameFactory::new()->appId(730)->toArray(),
            ],
        ],
    ]);
});

test('reports a game count of zero for a player who owns nothing', function (): void {
    expect(bodyOf(SteamResponse::ownedGames()))->toBe([
        'response' => ['game_count' => 0, 'games' => []],
    ]);
});

test('nests recently played games under the response key', function (): void {
    expect(bodyOf(SteamResponse::recentlyPlayedGames(
        RecentlyPlayedGamesFactory::new()->games(
            RecentlyPlayedGameFactory::new()->appId(381210),
            RecentlyPlayedGameFactory::new()->appId(730),
        ),
    )))->toBe([
        'response' => [
            'total_count' => 2,
            'games' => [
                RecentlyPlayedGameFactory::new()->appId(381210)->toArray(),
                RecentlyPlayedGameFactory::new()->appId(730)->toArray(),
            ],
        ],
    ]);
});

test('nests the steam level under the response key', function (): void {
    expect(bodyOf(SteamResponse::steamLevel(42)))->toBe([
        'response' => ['player_level' => 42],
    ]);
});

test('nests badges under the response key', function (): void {
    expect(bodyOf(SteamResponse::badges(PlayerBadgesFactory::new())))->toBe([
        'response' => PlayerBadgesFactory::new()->toArray(),
    ]);
});

test('nests community badge quests under the response key', function (): void {
    expect(bodyOf(SteamResponse::communityBadgeProgress(
        CommunityBadgeQuestFactory::new(),
        CommunityBadgeQuestFactory::new()->questId(202)->incomplete(),
    )))->toBe([
        'response' => [
            'quests' => [
                CommunityBadgeQuestFactory::new()->toArray(),
                CommunityBadgeQuestFactory::new()->questId(202)->incomplete()->toArray(),
            ],
        ],
    ]);
});

test('nests user stats under the playerstats key', function (): void {
    expect(bodyOf(SteamResponse::userStats(UserStatsFactory::new())))->toBe([
        'playerstats' => UserStatsFactory::new()->toArray(),
    ]);
});

test('nests player achievements under the playerstats key', function (): void {
    expect(bodyOf(SteamResponse::playerAchievements(PlayerAchievementsFactory::new())))->toBe([
        'playerstats' => PlayerAchievementsFactory::new()->toArray(),
    ]);
});

test('nests the player count under the response key', function (): void {
    expect(bodyOf(SteamResponse::currentPlayers(12_345)))->toBe([
        'response' => ['player_count' => 12_345],
    ]);
});

test('nests global achievements under the achievementpercentages key', function (): void {
    expect(bodyOf(SteamResponse::globalAchievements(
        GlobalAchievementFactory::new(),
        GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
    )))->toBe([
        'achievementpercentages' => [
            'achievements' => [
                GlobalAchievementFactory::new()->toArray(),
                GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5)->toArray(),
            ],
        ],
    ]);
});

test('nests the game schema under the game key', function (): void {
    expect(bodyOf(SteamResponse::gameSchema(GameSchemaFactory::new())))->toBe([
        'game' => GameSchemaFactory::new()->toArray(),
    ]);
});

test('answers an app publishing no schema with an empty game object', function (): void {
    expect(bodyOf(SteamResponse::gameSchema(GameSchemaFactory::new()->empty())))->toBe([
        'game' => [],
    ]);
});

test('reports a resolved vanity url as successful', function (): void {
    expect(bodyOf(SteamResponse::vanityUrl(fakedId())))->toBe([
        'response' => [
            'success' => 1,
            'steamid' => '76561198000000000',
        ],
    ]);
});

test('flags the version check as successful', function (): void {
    expect(bodyOf(SteamResponse::upToDateCheck(AppVersionCheckFactory::new()->outOfDate())))->toBe([
        'response' => [
            'success' => true,
            ...AppVersionCheckFactory::new()->outOfDate()->toArray(),
        ],
    ]);
});

test('flags the server list as successful', function (): void {
    expect(bodyOf(SteamResponse::serversAtAddress(
        GameServerFactory::new(),
        GameServerFactory::new()->address('108.181.62.21:27025'),
    )))->toBe([
        'response' => [
            'success' => true,
            'servers' => [
                GameServerFactory::new()->toArray(),
                GameServerFactory::new()->address('108.181.62.21:27025')->toArray(),
            ],
        ],
    ]);
});

test('answers an address with no servers with steams message', function (): void {
    expect(bodyOf(SteamResponse::serversAtAddress()))->toBe([
        'response' => [
            'success' => true,
            'servers' => [],
            'message' => 'No servers found at that address',
        ],
    ]);
});

test('puts the sdr config at the top level beside a success flag', function (): void {
    expect(bodyOf(SteamResponse::sdrConfig(SdrConfigFactory::new())))->toBe([
        ...SdrConfigFactory::new()->toArray(),
        'success' => true,
    ]);
});

test('nests app news under the appnews key', function (): void {
    expect(bodyOf(SteamResponse::appNews(
        AppNewsFactory::new()->items(NewsItemFactory::new())->total(3939),
    )))->toBe([
        'appnews' => [
            'appid' => 440,
            'newsitems' => [NewsItemFactory::new()->toArray()],
            'count' => 3939,
        ],
    ]);
});

// The values are GetServerInfo's own, so the Pacific string matches Steam's to the padded day.
test('puts the server time at the top level beside steams pacific string', function (): void {
    expect(bodyOf(SteamResponse::serverInfo(new DateTimeImmutable('2026-10-08 19:59:38 UTC'))))->toBe([
        'servertime' => 1791489578,
        'servertimestring' => 'Thu Oct  8 12:59:38 2026',
    ]);
});

test('writes the pacific string without moving a mutable date', function (): void {
    $time = new DateTime('2026-10-12 19:31:26 UTC');

    expect(bodyOf(SteamResponse::serverInfo($time)))->toBe([
        'servertime' => 1791833486,
        'servertimestring' => 'Mon Oct 12 12:31:26 2026',
    ])
        ->and($time->getTimezone()->getName())->toBe('UTC');
});

test('nests the supported api list under apilist.interfaces', function (): void {
    expect(bodyOf(SteamResponse::supportedApiList(
        WebApiInterfaceFactory::new(),
        WebApiInterfaceFactory::new()->name('IGCVersion_440'),
    )))->toBe([
        'apilist' => [
            'interfaces' => [
                WebApiInterfaceFactory::new()->toArray(),
                WebApiInterfaceFactory::new()->name('IGCVersion_440')->toArray(),
            ],
        ],
    ]);
});

test('answers an empty supported api list with no interfaces', function (): void {
    expect(bodyOf(SteamResponse::supportedApiList()))->toBe(['apilist' => ['interfaces' => []]]);
});

test('refuses a request outright with a 401', function (): void {
    expect(SteamResponse::profileNotPublic()->status())->toBe(401)
        ->and(bodyOf(SteamResponse::profileNotPublic()))->toBe(['message' => 'Access is denied.']);
});

test('hides a player service result behind a 200 with an empty response', function (): void {
    expect(SteamResponse::playerServiceNotPublic()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::playerServiceNotPublic()))->toBe(['response' => []]);
});

test('refuses stats with a 400 carrying an empty json object', function (): void {
    expect(SteamResponse::statsRefused()->status())->toBe(400)
        ->and(bodyOf(SteamResponse::statsRefused()))->toBe('{}');
});

test('reports an unknown app with a 404 carrying result 42', function (): void {
    expect(SteamResponse::appNotFound()->status())->toBe(404)
        ->and(bodyOf(SteamResponse::appNotFound()))->toBe(['response' => ['result' => 42]]);
});

test('refuses global achievements with a 403 carrying an empty json object', function (): void {
    expect(SteamResponse::globalAchievementsRefused()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::globalAchievementsRefused()))->toBe('{}');
});

test('reports an app the schema endpoint does not know with a bare 400', function (): void {
    expect(SteamResponse::schemaAppNotFound()->status())->toBe(400)
        ->and(bodyOf(SteamResponse::schemaAppNotFound()))->toBe('{}');
});

test('reports an unclaimed vanity url in the body, not the status', function (): void {
    expect(SteamResponse::vanityNotFound()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::vanityNotFound()))->toBe([
            'response' => [
                'success' => 42,
                'message' => 'No match',
            ],
        ]);
});

test('reports an unavailable version check in the body, not the status', function (): void {
    expect(SteamResponse::appVersionUnavailable()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::appVersionUnavailable()))->toBe([
            'response' => [
                'success' => false,
                'error' => "Couldn't get app info for the app specified.",
            ],
        ]);
});

test('reports a rejected server address in the body, not the status', function (): void {
    expect(SteamResponse::invalidServerAddress()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::invalidServerAddress()))->toBe([
            'response' => [
                'success' => false,
                'message' => "'addr' param should specify a valid IPv4 or IPv4:queryport",
            ],
        ]);
});

test('reports a refused server lookup in the body, not the status', function (): void {
    expect(SteamResponse::serversAtAddressRefused()->status())->toBe(200)
        ->and(bodyOf(SteamResponse::serversAtAddressRefused()))->toBe([
            'response' => [
                'success' => false,
                'message' => "Please don't call this API more often than once per minute for a given IP.",
            ],
        ]);
});

test('reports an app the sdr config does not know with a 500', function (): void {
    expect(SteamResponse::sdrConfigAppNotFound()->status())->toBe(500)
        ->and(bodyOf(SteamResponse::sdrConfigAppNotFound()))->toBe([
            'success' => false,
            'message' => 'Failed to get appinfo',
        ]);
});

test('refuses app news with a 403 carrying an empty json object', function (): void {
    expect(SteamResponse::appNewsUnavailable()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::appNewsUnavailable()))->toBe('{}');
});

test('answers a key the supported api list rejects with a 403 and an empty list', function (): void {
    expect(SteamResponse::supportedApiListInvalidKey()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::supportedApiListInvalidKey()))->toBe(['apilist' => ['interfaces' => []]]);
});

test('answers a rejected key with a 403 echoing the key parameter', function (): void {
    expect(SteamResponse::invalidApiKey()->status())->toBe(403)
        ->and(bodyOf(SteamResponse::invalidApiKey()))->toContain('key=');
});

test('answers a rejected key with a 401 echoing the key parameter', function (): void {
    expect(SteamResponse::apiKeyUnauthorized()->status())->toBe(401)
        ->and(bodyOf(SteamResponse::apiKeyUnauthorized()))->toContain('key=');
});

test('answers a missing key with a 400 in html', function (): void {
    expect(SteamResponse::apiKeyMissing()->status())->toBe(400)
        ->and(bodyOf(SteamResponse::apiKeyMissing()))->toContain("Parameter 'key' is missing");
});

test('throttles with a 429 that carries Retry-After only when given one', function (): void {
    expect(SteamResponse::tooManyRequests()->status())->toBe(429)
        ->and(bodyOf(SteamResponse::tooManyRequests()))->toBe([])
        ->and(SteamResponse::tooManyRequests()->headers()->all())->toBe([])
        ->and(SteamResponse::tooManyRequests(120)->headers()->all())->toBe(['Retry-After' => '120']);
});

// Round trips through the connector

test('feeds player summaries back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new()->personaName('Gabe')->online(),
        ),
    ]));

    $summaries = $connector->users()->summaries([fakedId()]);

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->personaName)->toBe('Gabe');
});

test('feeds ban records back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetPlayerBansRequest::class => SteamResponse::playerBans(PlayerBanFactory::new()->vacBanned(2)),
    ]));

    expect($connector->users()->bans([fakedId()])[0]->numberOfVacBans)->toBe(2);
});

test('feeds a friend list back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetFriendListRequest::class => SteamResponse::friendList(FriendFactory::new(), FriendFactory::new()),
    ]));

    expect($connector->users()->friends(fakedId()))->toHaveCount(2);
});

test('feeds a group list back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetUserGroupListRequest::class => SteamResponse::userGroupList(UserGroupFactory::new()->gid('103582791429521413')),
    ]));

    expect($connector->users()->groups(fakedId())[0]->gid)->toBe('103582791429521413');
});

test('feeds owned games back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetOwnedGamesRequest::class => SteamResponse::ownedGames(OwnedGameFactory::new()->withAppInfo()),
    ]));

    expect($connector->players()->ownedGames(fakedId())[0]->name)->toBe('Dead by Daylight');
});

test('feeds recently played games back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetRecentlyPlayedGamesRequest::class => SteamResponse::recentlyPlayedGames(
            RecentlyPlayedGamesFactory::new()->games(
                RecentlyPlayedGameFactory::new()->name('Counter-Strike 2'),
            ),
        ),
    ]));

    expect($connector->players()->recentlyPlayedGames(fakedId())->games[0]->name)->toBe('Counter-Strike 2');
});

test('feeds the steam level back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([GetSteamLevelRequest::class => SteamResponse::steamLevel(42)]));

    expect($connector->players()->steamLevel(fakedId()))->toBe(42);
});

test('feeds badges back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetBadgesRequest::class => SteamResponse::badges(
            PlayerBadgesFactory::new()->badges(BadgeFactory::new()->communityItem()),
        ),
    ]));

    $badges = $connector->players()->badges(fakedId());

    expect($badges->playerLevel)->toBe(12)
        ->and($badges->badges[0]->communityItemId)->toBe('2101234567890123456');
});

test('feeds community badge progress back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetCommunityBadgeProgressRequest::class => SteamResponse::communityBadgeProgress(
            CommunityBadgeQuestFactory::new(),
            CommunityBadgeQuestFactory::new()->questId(202)->incomplete(),
        ),
    ]));

    $quests = $connector->players()->communityBadgeProgress(fakedId());

    expect($quests)->toHaveCount(2)
        ->and($quests[1]->questId)->toBe(202)
        ->and($quests[1]->completed)->toBeFalse();
});

test('feeds user stats back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetUserStatsForGameRequest::class => SteamResponse::userStats(UserStatsFactory::new()),
    ]));

    expect($connector->stats()->userStats(fakedId(), appId: 381210)->stats)->toHaveCount(1);
});

test('feeds player achievements back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetPlayerAchievementsRequest::class => SteamResponse::playerAchievements(
            PlayerAchievementsFactory::new()->achievements(
                PlayerAchievementFactory::new(),
                PlayerAchievementFactory::new()->locked(),
            ),
        ),
    ]));

    expect($connector->stats()->achievements(fakedId(), appId: 381210)->achievements)->toHaveCount(2);
});

test('feeds the player count back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => SteamResponse::currentPlayers(12_345),
    ]));

    expect($connector->stats()->currentPlayers(appId: 381210))->toBe(12_345);
});

test('feeds global achievements back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetGlobalAchievementPercentagesForAppRequest::class => SteamResponse::globalAchievements(
            GlobalAchievementFactory::new(),
            GlobalAchievementFactory::new()->apiName('ACH_ESCAPE')->percent(12.5),
        ),
    ]));

    $achievements = $connector->stats()->globalAchievements(gameId: 381210);

    expect($achievements)->toHaveCount(2)
        ->and($achievements[1]->apiName)->toBe('ACH_ESCAPE')
        ->and($achievements[1]->percent)->toBe(12.5);
});

test('feeds the game schema back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetSchemaForGameRequest::class => SteamResponse::gameSchema(
            GameSchemaFactory::new()->gameName('Portal 2'),
        ),
    ]));

    $schema = $connector->stats()->schema(appId: 620);

    expect($schema->gameName)->toBe('Portal 2')
        ->and($schema->stats)->toHaveCount(1)
        ->and($schema->achievements)->toHaveCount(1);
});

test('feeds a resolved vanity url back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        ResolveVanityUrlRequest::class => SteamResponse::vanityUrl(fakedId()),
    ]));

    expect($connector->users()->resolveVanityUrl('gabelogannewell')->value)->toBe('76561198000000000');
});

test('feeds a version check back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        UpToDateCheckRequest::class => SteamResponse::upToDateCheck(AppVersionCheckFactory::new()->outOfDate()),
    ]));

    $check = $connector->apps()->upToDateCheck(appId: 440, version: 1);

    expect($check->isUpToDate)->toBeFalse()
        ->and($check->requiredVersion)->toBe(10828683)
        ->and($check->message)->toBe('Your server is out of date, please upgrade');
});

test('feeds a server list back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetServersAtAddressRequest::class => SteamResponse::serversAtAddress(
            GameServerFactory::new()->withoutSpectatorPort(),
        ),
    ]));

    $servers = $connector->apps()->serversAtAddress('108.181.62.21');

    expect($servers)->toHaveCount(1)
        ->and($servers[0]->address)->toBe('108.181.62.21:27015')
        ->and($servers[0]->spectatorPort)->toBeNull();
});

test('feeds an sdr config back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetSdrConfigRequest::class => SteamResponse::sdrConfig(SdrConfigFactory::new()->pointsOfPresence(
            SdrPointOfPresenceFactory::new()->code('waw')->latitude(52.22)->longitude(21),
            SdrPointOfPresenceFactory::new()->code('eat')->aliases('mwh')->withoutRelays(),
        )),
    ]));

    $pointsOfPresence = $connector->apps()->sdrConfig(appId: 730)->pointsOfPresence;

    expect(array_keys($pointsOfPresence))->toBe(['waw', 'eat'])
        ->and($pointsOfPresence['waw']->latitude)->toBe(52.22)
        ->and($pointsOfPresence['waw']->longitude)->toBe(21.0)
        ->and($pointsOfPresence['eat']->aliases)->toBe(['mwh'])
        ->and($pointsOfPresence['eat']->relays)->toBeEmpty();
});

test('feeds app news back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetNewsForAppRequest::class => SteamResponse::appNews(
            AppNewsFactory::new()
                ->items(
                    NewsItemFactory::new()->communityAnnouncement()->tags('patchnotes'),
                    NewsItemFactory::new()->withoutAuthor(),
                )
                ->total(3939),
        ),
    ]));

    $news = $connector->news()->appNews(appId: 440, count: 2);

    expect($news->total)->toBe(3939)
        ->and($news->items)->toHaveCount(2)
        ->and($news->items[0]->isCommunityAnnouncement)->toBeTrue()
        ->and($news->items[0]->tags)->toBe(['patchnotes'])
        ->and($news->items[1]->author)->toBeNull()
        ->and($news->items[1]->tags)->toBeEmpty();
});

test('pages a news feed the way steam does', function (): void {
    $items = newsFeedItems(3);

    $connector = fakedConnector(new MockClient([GetNewsForAppRequest::class => SteamResponse::newsFeed(...$items)]));

    $first = $connector->news()->appNews(appId: 570, count: 2);
    $next = $connector->news()->appNews(appId: 570, count: 2, endDate: $items[1]->make()->publishedAt);

    expect($first->appId)->toBe(570)
        ->and(array_column($first->items, 'id'))->toBe(['1', '2'])
        ->and($first->total)->toBe(3)
        ->and(array_column($next->items, 'id'))->toBe(['2', '3'])
        ->and($next->total)->toBe(2);
});

test('answers a news feed request without a count with twenty items', function (): void {
    $connector = fakedConnector(new MockClient([GetNewsForAppRequest::class => SteamResponse::newsFeed(...newsFeedItems(21))]));

    $news = $connector->news()->appNews(appId: 440);

    expect($news->items)->toHaveCount(20)
        ->and($news->total)->toBe(21);
});

test('answers an empty news feed with no news', function (): void {
    $connector = fakedConnector(new MockClient([GetNewsForAppRequest::class => SteamResponse::newsFeed()]));

    $news = $connector->news()->appNews(appId: 440);

    expect($news->items)->toBeEmpty()
        ->and($news->total)->toBe(0);
});

test('refuses to answer anything but the news endpoint from a news feed', function (): void {
    $connector = fakedConnector(new MockClient([SteamConnector::class => SteamResponse::newsFeed()]));

    expect(fn (): int => $connector->stats()->currentPlayers(appId: 440))
        ->toThrow(LogicException::class, 'A faked news feed answers GetNewsForAppRequest only.');
});

test('feeds the server time back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetServerInfoRequest::class => SteamResponse::serverInfo(new DateTimeImmutable('2026-10-08 21:59:38 +02:00')),
    ]));

    $time = $connector->webApi()->serverTime();

    expect($time->format(DATE_ATOM))->toBe('2026-10-08T19:59:38+00:00');
});

test('feeds the supported api list back through the connector', function (): void {
    $connector = fakedConnector(new MockClient([
        GetSupportedApiListRequest::class => SteamResponse::supportedApiList(
            WebApiInterfaceFactory::new()->name('IGCVersion_440')->methods(
                WebApiMethodFactory::new()->name('GetClientVersion')->withoutDescription()->parameters(),
            ),
            WebApiInterfaceFactory::new()->methods(
                WebApiMethodFactory::new()->name('PollAuthSessionStatus')->post()->parameters(
                    WebApiParameterFactory::new()->name('client_id')->type('uint64')->withoutDescription(),
                    WebApiParameterFactory::new()->emptyDescription(),
                    WebApiParameterFactory::new()->optional(),
                ),
            ),
        ),
    ]));

    [$gcVersion, $authentication] = $connector->webApi()->supportedApis();
    $parameters = $authentication->methods[0]->parameters;

    expect($gcVersion->name)->toBe('IGCVersion_440')
        ->and($gcVersion->methods[0]->description)->toBeNull()
        ->and($gcVersion->methods[0]->httpMethod)->toBe('GET')
        ->and($gcVersion->methods[0]->parameters)->toBeEmpty()
        ->and($authentication->methods[0]->httpMethod)->toBe('POST')
        ->and($parameters[0]->type)->toBe('uint64')
        ->and($parameters[0]->description)->toBeNull()
        ->and($parameters[1]->description)->toBeNull()
        ->and($parameters[2]->isOptional)->toBeTrue();
});

// Failures — these pin the contract with the SDK's exception mapping

test('raises a not public profile from a refused friend list', function (): void {
    $connector = fakedConnector(new MockClient([GetFriendListRequest::class => SteamResponse::profileNotPublic()]));

    expect(fn (): array => $connector->users()->friends(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises a not public profile from a refused group list', function (): void {
    $connector = fakedConnector(new MockClient([GetUserGroupListRequest::class => SteamResponse::profileNotPublic()]));

    expect(fn (): array => $connector->users()->groups(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises a not public profile from owned games without a game count', function (): void {
    $connector = fakedConnector(new MockClient([GetOwnedGamesRequest::class => SteamResponse::playerServiceNotPublic()]));

    expect(fn (): array => $connector->players()->ownedGames(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises a not public profile from recently played games without a total count', function (): void {
    $connector = fakedConnector(new MockClient([GetRecentlyPlayedGamesRequest::class => SteamResponse::playerServiceNotPublic()]));

    expect(fn (): RecentlyPlayedGames => $connector->players()->recentlyPlayedGames(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises a not public profile from a steam level without a player level', function (): void {
    $connector = fakedConnector(new MockClient([GetSteamLevelRequest::class => SteamResponse::playerServiceNotPublic()]));

    expect(fn (): int => $connector->players()->steamLevel(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises a not public profile from badges without a player level', function (): void {
    $connector = fakedConnector(new MockClient([GetBadgesRequest::class => SteamResponse::playerServiceNotPublic()]));

    expect(fn (): PlayerBadges => $connector->players()->badges(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises a not public profile from community badge progress without quests', function (): void {
    $connector = fakedConnector(new MockClient([GetCommunityBadgeProgressRequest::class => SteamResponse::playerServiceNotPublic()]));

    expect(fn (): array => $connector->players()->communityBadgeProgress(fakedId()))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises a not public profile from refused user stats', function (): void {
    $connector = fakedConnector(new MockClient([GetUserStatsForGameRequest::class => SteamResponse::statsRefused()]));

    expect(fn (): UserStats => $connector->stats()->userStats(fakedId(), appId: 381210))
        ->toThrow(ProfileNotPublicException::class);
});

test('raises unavailable stats from refused player achievements', function (): void {
    $connector = fakedConnector(new MockClient([GetPlayerAchievementsRequest::class => SteamResponse::statsRefused()]));

    expect(fn (): PlayerAchievements => $connector->stats()->achievements(fakedId(), appId: 381210))
        ->toThrow(StatsUnavailableException::class);
});

test('raises a missing app from an unknown app id', function (): void {
    $connector = fakedConnector(new MockClient([GetNumberOfCurrentPlayersRequest::class => SteamResponse::appNotFound()]));

    expect(fn (): int => $connector->stats()->currentPlayers(appId: 1))
        ->toThrow(AppNotFoundException::class);
});

// The connector reads a 403 as a rejected key or a hidden profile; the request
// claims this one first, which is what the fake has to keep working.
test('raises unavailable stats from refused global achievements', function (): void {
    $connector = fakedConnector(new MockClient([
        GetGlobalAchievementPercentagesForAppRequest::class => SteamResponse::globalAchievementsRefused(),
    ]));

    expect(fn (): array => $connector->stats()->globalAchievements(gameId: 381210))
        ->toThrow(StatsUnavailableException::class);
});

test('raises a missing app from an app id the schema endpoint does not know', function (): void {
    $connector = fakedConnector(new MockClient([GetSchemaForGameRequest::class => SteamResponse::schemaAppNotFound()]));

    expect(fn (): GameSchema => $connector->stats()->schema(appId: 1))
        ->toThrow(AppNotFoundException::class);
});

test('raises a missing user from an unclaimed vanity url', function (): void {
    $connector = fakedConnector(new MockClient([ResolveVanityUrlRequest::class => SteamResponse::vanityNotFound()]));

    expect(fn (): SteamId => $connector->users()->resolveVanityUrl('nobody'))
        ->toThrow(SteamUserNotFoundException::class);
});

test('raises an unavailable app version from an unsuccessful version check', function (): void {
    $connector = fakedConnector(new MockClient([UpToDateCheckRequest::class => SteamResponse::appVersionUnavailable()]));

    expect(fn (): AppVersionCheck => $connector->apps()->upToDateCheck(appId: 999999999, version: 1))
        ->toThrow(AppVersionUnavailableException::class);
});

test('raises an invalid server address from a rejected address', function (): void {
    $connector = fakedConnector(new MockClient([GetServersAtAddressRequest::class => SteamResponse::invalidServerAddress()]));

    expect(fn (): array => $connector->apps()->serversAtAddress('not-an-ip'))
        ->toThrow(InvalidServerAddressException::class);
});

// InvalidServerAddressException is a SteamApiException too, so only the message
// says the SDK fell through to the root type.
test('raises the root exception from a refused server lookup', function (): void {
    $connector = fakedConnector(new MockClient([GetServersAtAddressRequest::class => SteamResponse::serversAtAddressRefused()]));

    expect(fn (): array => $connector->apps()->serversAtAddress('127.0.0.1'))
        ->toThrow(SteamApiException::class, 'once per minute for a given IP');
});

test('raises a missing app from an app id the sdr config does not know', function (): void {
    $connector = fakedConnector(new MockClient([GetSdrConfigRequest::class => SteamResponse::sdrConfigAppNotFound()]));

    expect(fn (): SdrConfig => $connector->apps()->sdrConfig(appId: 999999999))
        ->toThrow(AppNotFoundException::class, 'No Steam app found for app ID 999999999.');
});

// Like global achievements, the request claims this 403 before the connector can
// read it as a rejected key or a hidden profile.
test('raises unavailable news from a 403 on the news endpoint', function (): void {
    $connector = fakedConnector(new MockClient([GetNewsForAppRequest::class => SteamResponse::appNewsUnavailable()]));

    expect(fn (): AppNews => $connector->news()->appNews(appId: 480))
        ->toThrow(AppNewsUnavailableException::class, 'GetNewsForApp: Steam returned no news for app 480');
});

// The connector reads a 403 without `key=` as a hidden profile; the request claims this one first.
test('raises an invalid api key from a key the supported api list rejects', function (): void {
    $connector = fakedConnector(new MockClient([GetSupportedApiListRequest::class => SteamResponse::supportedApiListInvalidKey()]));

    expect(fn (): array => $connector->webApi()->supportedApis())->toThrow(
        InvalidApiKeyException::class,
        'GetSupportedAPIList: Steam rejected the API key. Check that it is valid and active.',
    );
});

test('claims an app the sdr config does not know before the retry loop', function (): void {
    $mock = new MockClient([GetSdrConfigRequest::class => SteamResponse::sdrConfigAppNotFound()]);
    $connector = fakedConnector($mock, new SteamConfig('test-key', tries: 3));

    expect(fn (): SdrConfig => $connector->apps()->sdrConfig(appId: 999999999))
        ->toThrow(AppNotFoundException::class);

    $mock->assertSentCount(1);
});

test('raises an invalid api key from a rejected key', function (): void {
    $connector = fakedConnector(new MockClient([GetPlayerSummariesRequest::class => SteamResponse::invalidApiKey()]));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});

test('raises an invalid api key from a key rejected with a 401', function (): void {
    $connector = fakedConnector(new MockClient([GetCommunityBadgeProgressRequest::class => SteamResponse::apiKeyUnauthorized()]));

    expect(fn (): array => $connector->players()->communityBadgeProgress(fakedId()))
        ->toThrow(InvalidApiKeyException::class);
});

test('raises an invalid api key from a request sent without one', function (): void {
    $connector = fakedConnector(new MockClient([GetPlayerSummariesRequest::class => SteamResponse::apiKeyMissing()]));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});

test('raises a connection failure from a request that never reaches steam', function (): void {
    $connector = fakedConnector(new MockClient([GetPlayerSummariesRequest::class => SteamResponse::connectionFailed()]));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))->toThrow(
        SteamConnectionException::class,
        'Could not reach the Steam Web API: cURL error 28: Operation timed out after 10000 milliseconds',
    );
});

test('retries a connection failure without recording any attempt', function (): void {
    $attempts = 0;

    $mock = new MockClient([
        GetPlayerSummariesRequest::class => function () use (&$attempts): MockResponse {
            $attempts++;

            return SteamResponse::connectionFailed();
        },
    ]);
    $connector = fakedConnector($mock, new SteamConfig('test-key', tries: 3));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))->toThrow(SteamConnectionException::class)
        ->and($attempts)->toBe(3)
        ->and($mock->getRecordedResponses())->toBeEmpty();
});

test('raises a rate limit from a 429 and holds the key for the cool-down or Retry-After', function (?int $retryAfter, int $seconds): void {
    $connector = fakedConnector(new MockClient([
        GetPlayerSummariesRequest::class => SteamResponse::tooManyRequests($retryAfter),
    ]));

    $before = time();

    expect(fn (): array => $connector->users()->summaries([fakedId()]))
        ->toThrow(function (SteamRateLimitException $exception) use ($before, $seconds): void {
            expect($exception->limit->getName())->toBe('SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:too_many_attempts_limit')
                ->and($exception->limit->getExpiryTimestamp())
                ->toBeGreaterThanOrEqual($before + $seconds)
                ->toBeLessThanOrEqual(time() + $seconds);
        });
})->with([
    'without Retry-After' => [null, 60],
    'with Retry-After' => [120, 120],
]);
