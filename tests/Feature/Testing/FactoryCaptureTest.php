<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetGlobalAchievementPercentagesForAppRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
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
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrRelayFactory;
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
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\SchemaAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\SchemaStatFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiMethodFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiParameterFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

covers(
    SteamResponse::class,
    AppNewsFactory::class,
    AppVersionCheckFactory::class,
    BadgeFactory::class,
    CommunityBadgeQuestFactory::class,
    FriendFactory::class,
    GameSchemaFactory::class,
    GameServerFactory::class,
    GlobalAchievementFactory::class,
    NewsItemFactory::class,
    OwnedGameFactory::class,
    PlayerAchievementFactory::class,
    PlayerAchievementsFactory::class,
    PlayerBadgesFactory::class,
    PlayerBanFactory::class,
    PlayerSummaryFactory::class,
    RecentlyPlayedGameFactory::class,
    RecentlyPlayedGamesFactory::class,
    SchemaAchievementFactory::class,
    SchemaStatFactory::class,
    SdrConfigFactory::class,
    SdrPointOfPresenceFactory::class,
    SdrRelayFactory::class,
    UserGroupFactory::class,
    UserStatAchievementFactory::class,
    UserStatFactory::class,
    UserStatsFactory::class,
    WebApiInterfaceFactory::class,
    WebApiMethodFactory::class,
    WebApiParameterFactory::class,
);

/**
 * Every path in a JSON body mapped to the types found there, with list items merged under `[]`.
 *
 * @param  array<string, list<string>>  $shape
 * @return array<string, list<string>>
 */
function jsonShape(mixed $json, string $path = '$', array $shape = []): array
{
    $type = match (true) {
        $json instanceof stdClass => 'object',
        is_array($json) => 'list',
        default => get_debug_type($json),
    };

    $shape[$path] = array_values(array_unique([...$shape[$path] ?? [], $type]));

    foreach (is_array($json) ? $json : [] as $item) {
        $shape = jsonShape($item, "{$path}[]", $shape);
    }

    foreach ($json instanceof stdClass ? get_object_vars($json) : [] as $key => $value) {
        $shape = jsonShape($value, "{$path}.{$key}", $shape);
    }

    return $shape;
}

function capturedData(string $fixture): mixed
{
    /** @var object{data: mixed} $captured */
    $captured = json_decode((string) file_get_contents(__DIR__."/../../Fixtures/Saloon/{$fixture}.json"), flags: JSON_THROW_ON_ERROR);

    return $captured->data;
}

function dtoType(mixed $dto): string
{
    return is_array($dto)
        ? 'list<'.implode('|', array_unique(array_map(get_debug_type(...), $dto))).'>'
        : get_debug_type($dto);
}

dataset('factory defaults', [
    'PlayerSummaryFactory' => [
        new GetPlayerSummariesRequest([steamId()]),
        static fn (SteamFake $fake): SteamFake => $fake->users()->summaries(PlayerSummaryFactory::new()),
        ['ISteamUser/GetPlayerSummaries/default', 'ISteamUser/GetPlayerSummaries/friends-only', 'ISteamUser/GetPlayerSummaries/hidden'],
    ],
    'PlayerBanFactory' => [
        new GetPlayerBansRequest([steamId()]),
        static fn (SteamFake $fake): SteamFake => $fake->users()->bans(PlayerBanFactory::new()),
        ['ISteamUser/GetPlayerBans/default', 'ISteamUser/GetPlayerBans/community_banned', 'ISteamUser/GetPlayerBans/economy_banned'],
    ],
    'FriendFactory' => [
        friendListRequest(),
        static fn (SteamFake $fake): SteamFake => $fake->users()->friends(FriendFactory::new()),
        ['ISteamUser/GetFriendList/default'],
    ],
    'UserGroupFactory' => [
        new GetUserGroupListRequest(steamId()),
        static fn (SteamFake $fake): SteamFake => $fake->users()->groups(UserGroupFactory::new()),
        ['ISteamUser/GetUserGroupList/default'],
    ],
    'OwnedGameFactory' => [
        new GetOwnedGamesRequest(steamId()),
        static fn (SteamFake $fake): SteamFake => $fake->players()->ownedGames(OwnedGameFactory::new()),
        ['IPlayerService/GetOwnedGames/default'],
    ],
    'RecentlyPlayedGamesFactory' => [
        new GetRecentlyPlayedGamesRequest(steamId()),
        static fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()),
        ['IPlayerService/GetRecentlyPlayedGames/default', 'IPlayerService/GetRecentlyPlayedGames/limited'],
    ],
    'RecentlyPlayedGameFactory' => [
        new GetRecentlyPlayedGamesRequest(steamId()),
        static fn (SteamFake $fake): SteamFake => $fake->players()->recentlyPlayedGames(RecentlyPlayedGamesFactory::new()->games(RecentlyPlayedGameFactory::new())),
        ['IPlayerService/GetRecentlyPlayedGames/default', 'IPlayerService/GetRecentlyPlayedGames/limited'],
    ],
    'PlayerBadgesFactory' => [
        new GetBadgesRequest(steamId()),
        static fn (SteamFake $fake): SteamFake => $fake->players()->badges(PlayerBadgesFactory::new()),
        ['IPlayerService/GetBadges/default', 'IPlayerService/GetBadges/zero'],
    ],
    'BadgeFactory' => [
        new GetBadgesRequest(steamId()),
        static fn (SteamFake $fake): SteamFake => $fake->players()->badges(PlayerBadgesFactory::new()->badges(BadgeFactory::new())),
        ['IPlayerService/GetBadges/default'],
    ],
    'CommunityBadgeQuestFactory' => [
        new GetCommunityBadgeProgressRequest(steamId()),
        static fn (SteamFake $fake): SteamFake => $fake->players()->communityBadgeProgress(CommunityBadgeQuestFactory::new()),
        ['IPlayerService/GetCommunityBadgeProgress/default'],
    ],
    'AppVersionCheckFactory' => [
        new UpToDateCheckRequest(440, 1),
        static fn (SteamFake $fake): SteamFake => $fake->apps()->upToDateCheck(AppVersionCheckFactory::new()),
        ['ISteamApps/UpToDateCheck/up-to-date', 'ISteamApps/UpToDateCheck/out-of-date'],
    ],
    'GameServerFactory' => [
        new GetServersAtAddressRequest('1.2.3.4'),
        static fn (SteamFake $fake): SteamFake => $fake->apps()->serversAtAddress(GameServerFactory::new()),
        ['ISteamApps/GetServersAtAddress/default', 'ISteamApps/GetServersAtAddress/rust'],
    ],
    'SdrConfigFactory' => [
        new GetSdrConfigRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfig(SdrConfigFactory::new()),
        ['ISteamApps/GetSDRConfig/default', 'ISteamApps/GetSDRConfig/steam-wide'],
    ],
    'SdrPointOfPresenceFactory' => [
        new GetSdrConfigRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfig(SdrConfigFactory::new()->pointsOfPresence(SdrPointOfPresenceFactory::new())),
        ['ISteamApps/GetSDRConfig/default', 'ISteamApps/GetSDRConfig/steam-wide'],
    ],
    'SdrRelayFactory' => [
        new GetSdrConfigRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->apps()->sdrConfig(
            SdrConfigFactory::new()->pointsOfPresence(SdrPointOfPresenceFactory::new()->relays(SdrRelayFactory::new())),
        ),
        ['ISteamApps/GetSDRConfig/default', 'ISteamApps/GetSDRConfig/steam-wide'],
    ],
    'AppNewsFactory' => [
        new GetNewsForAppRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->news()->appNews(AppNewsFactory::new()),
        ['ISteamNews/GetNewsForApp/default', 'ISteamNews/GetNewsForApp/end-date', 'ISteamNews/GetNewsForApp/excerpt', 'ISteamNews/GetNewsForApp/parent-app'],
    ],
    'NewsItemFactory' => [
        new GetNewsForAppRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->news()->appNews(AppNewsFactory::new()->items(NewsItemFactory::new())),
        ['ISteamNews/GetNewsForApp/default', 'ISteamNews/GetNewsForApp/end-date', 'ISteamNews/GetNewsForApp/excerpt', 'ISteamNews/GetNewsForApp/parent-app'],
    ],
    'GameSchemaFactory' => [
        new GetSchemaForGameRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->schema(GameSchemaFactory::new()),
        ['ISteamUserStats/GetSchemaForGame/default', 'ISteamUserStats/GetSchemaForGame/localized', 'ISteamUserStats/GetSchemaForGame/unnamed-game'],
    ],
    'SchemaStatFactory' => [
        new GetSchemaForGameRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->schema(GameSchemaFactory::new()->stats(SchemaStatFactory::new())),
        ['ISteamUserStats/GetSchemaForGame/default', 'ISteamUserStats/GetSchemaForGame/localized', 'ISteamUserStats/GetSchemaForGame/unnamed-game'],
    ],
    'SchemaAchievementFactory' => [
        new GetSchemaForGameRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->schema(GameSchemaFactory::new()->achievements(SchemaAchievementFactory::new())),
        ['ISteamUserStats/GetSchemaForGame/default', 'ISteamUserStats/GetSchemaForGame/localized', 'ISteamUserStats/GetSchemaForGame/unnamed-game'],
    ],
    'GlobalAchievementFactory' => [
        new GetGlobalAchievementPercentagesForAppRequest(440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->globalAchievements(GlobalAchievementFactory::new()),
        ['ISteamUserStats/GetGlobalAchievementPercentagesForApp/default'],
    ],
    'PlayerAchievementsFactory' => [
        new GetPlayerAchievementsRequest(steamId(), 440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->achievements(PlayerAchievementsFactory::new()),
        ['ISteamUserStats/GetPlayerAchievements/default', 'ISteamUserStats/GetPlayerAchievements/localized'],
    ],
    'PlayerAchievementFactory' => [
        new GetPlayerAchievementsRequest(steamId(), 440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->achievements(PlayerAchievementsFactory::new()->achievements(PlayerAchievementFactory::new())),
        ['ISteamUserStats/GetPlayerAchievements/default', 'ISteamUserStats/GetPlayerAchievements/localized'],
    ],
    'UserStatsFactory' => [
        new GetUserStatsForGameRequest(steamId(), 440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->userStats(UserStatsFactory::new()),
        ['ISteamUserStats/GetUserStatsForGame/default'],
    ],
    'UserStatFactory' => [
        new GetUserStatsForGameRequest(steamId(), 440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->userStats(UserStatsFactory::new()->stats(UserStatFactory::new())),
        ['ISteamUserStats/GetUserStatsForGame/default'],
    ],
    'UserStatAchievementFactory' => [
        new GetUserStatsForGameRequest(steamId(), 440),
        static fn (SteamFake $fake): SteamFake => $fake->stats()->userStats(UserStatsFactory::new()->achievements(UserStatAchievementFactory::new())),
        ['ISteamUserStats/GetUserStatsForGame/default'],
    ],
    'WebApiInterfaceFactory' => [
        new GetSupportedApiListRequest,
        static fn (SteamFake $fake): SteamFake => $fake->webApi()->supportedApiList(WebApiInterfaceFactory::new()),
        ['ISteamWebAPIUtil/GetSupportedAPIList/keyed', 'ISteamWebAPIUtil/GetSupportedAPIList/anonymous'],
    ],
    'WebApiMethodFactory' => [
        new GetSupportedApiListRequest,
        static fn (SteamFake $fake): SteamFake => $fake->webApi()->supportedApiList(WebApiInterfaceFactory::new()->methods(WebApiMethodFactory::new())),
        ['ISteamWebAPIUtil/GetSupportedAPIList/keyed', 'ISteamWebAPIUtil/GetSupportedAPIList/anonymous'],
    ],
    'WebApiParameterFactory' => [
        new GetSupportedApiListRequest,
        static fn (SteamFake $fake): SteamFake => $fake->webApi()->supportedApiList(
            WebApiInterfaceFactory::new()->methods(WebApiMethodFactory::new()->parameters(WebApiParameterFactory::new())),
        ),
        ['ISteamWebAPIUtil/GetSupportedAPIList/keyed', 'ISteamWebAPIUtil/GetSupportedAPIList/anonymous'],
    ],
]);

test('sends no key or type steam does not', function (Request $request, Closure $faked, array $captures): void {
    $steam = array_reduce($captures, static fn (array $shape, string $capture): array => jsonShape(capturedData($capture), shape: $shape), []);
    $unknown = [];

    foreach (jsonShape(json_decode(answerFrom($faked(new SteamFake), $request)->body(), flags: JSON_THROW_ON_ERROR)) as $path => $types) {
        $foreign = array_values(array_diff($types, $steam[$path] ?? []));

        if ($foreign !== []) {
            $unknown[$path] = $foreign;
        }
    }

    expect($unknown)->toBe([]);
})->with('factory defaults');

test('reads its default back through the request like its capture', function (Request $request, Closure $faked, array $captures): void {
    $read = static fn (MockClient $mockClient): string => dtoType(fakedConnector($mockClient)->send($request)->dto());

    expect($read($faked(new SteamFake)))->toBe($read(new MockClient([MockResponse::fixture($captures[0])])));
})->with('factory defaults');
