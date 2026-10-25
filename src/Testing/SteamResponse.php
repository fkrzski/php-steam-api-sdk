<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\CommunityBadgeQuestFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\OwnedGameFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\PlayerBadgesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\RecentlyPlayedGamesFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\AppVersionCheckFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\GameServerFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrConfigFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\AppNewsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\FriendFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerBanFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\UserGroupFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GameSchemaFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\GlobalAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\UserStatsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Exception\ConnectException;
use LogicException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * Wraps the factory payloads in the envelope each Steam endpoint answers with.
 *
 * Every endpoint nests its payload its own way and refuses in its own shape, so both
 * live here rather than in every test that fakes a call.
 */
final class SteamResponse
{
    public static function playerSummaries(PlayerSummaryFactory ...$players): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'players' => array_map(
                    static fn (PlayerSummaryFactory $player): array => $player->toArray(),
                    $players,
                ),
            ],
        ]);
    }

    /**
     * Ban records sit at the top level, without the `response` wrapper every other
     * ISteamUser endpoint uses.
     */
    public static function playerBans(PlayerBanFactory ...$bans): MockResponse
    {
        return MockResponse::make([
            'players' => array_map(
                static fn (PlayerBanFactory $ban): array => $ban->toArray(),
                $bans,
            ),
        ]);
    }

    public static function friendList(FriendFactory ...$friends): MockResponse
    {
        return MockResponse::make([
            'friendslist' => [
                'friends' => array_map(
                    static fn (FriendFactory $friend): array => $friend->toArray(),
                    $friends,
                ),
            ],
        ]);
    }

    public static function userGroupList(UserGroupFactory ...$groups): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => true,
                'groups' => array_map(
                    static fn (UserGroupFactory $group): array => $group->toArray(),
                    $groups,
                ),
            ],
        ]);
    }

    /**
     * `game_count` is what tells the SDK the profile is public — see
     * {@see self::playerServiceNotPublic()} for the response that omits it.
     */
    public static function ownedGames(OwnedGameFactory ...$games): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'game_count' => count($games),
                'games' => array_map(
                    static fn (OwnedGameFactory $game): array => $game->toArray(),
                    $games,
                ),
            ],
        ]);
    }

    /**
     * `total_count` is Steam's own total for the window, so it is independent of how
     * many games the payload lists — see {@see RecentlyPlayedGamesFactory::totalCount()}.
     */
    public static function recentlyPlayedGames(RecentlyPlayedGamesFactory $games): MockResponse
    {
        return MockResponse::make([
            'response' => $games->toArray(),
        ]);
    }

    public static function steamLevel(int $level): MockResponse
    {
        return MockResponse::make([
            'response' => ['player_level' => $level],
        ]);
    }

    /**
     * `player_level` is what tells the SDK the profile is public — a hidden one
     * answers with the empty `response` object {@see self::playerServiceNotPublic()}
     * returns.
     */
    public static function badges(PlayerBadgesFactory $badges): MockResponse
    {
        return MockResponse::make([
            'response' => $badges->toArray(),
        ]);
    }

    public static function communityBadgeProgress(CommunityBadgeQuestFactory ...$quests): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'quests' => array_map(
                    static fn (CommunityBadgeQuestFactory $quest): array => $quest->toArray(),
                    $quests,
                ),
            ],
        ]);
    }

    public static function userStats(UserStatsFactory $stats): MockResponse
    {
        return MockResponse::make([
            'playerstats' => $stats->toArray(),
        ]);
    }

    public static function playerAchievements(PlayerAchievementsFactory $achievements): MockResponse
    {
        return MockResponse::make([
            'playerstats' => $achievements->toArray(),
        ]);
    }

    public static function currentPlayers(int $count): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'player_count' => $count,
                'result' => 1,
            ],
        ]);
    }

    public static function globalAchievements(GlobalAchievementFactory ...$achievements): MockResponse
    {
        return MockResponse::make([
            'achievementpercentages' => [
                'achievements' => array_map(
                    static fn (GlobalAchievementFactory $achievement): array => $achievement->toArray(),
                    $achievements,
                ),
            ],
        ]);
    }

    public static function gameSchema(GameSchemaFactory $schema): MockResponse
    {
        $game = $schema->toArray();

        // An empty array encodes as `[]`, where Steam sends `{}`.
        return MockResponse::make([
            'game' => $game === [] ? (object) [] : $game,
        ]);
    }

    public static function vanityUrl(SteamId $steamId): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'steamid' => $steamId->value,
                'success' => 1,
            ],
        ]);
    }

    public static function upToDateCheck(AppVersionCheckFactory $check): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => true,
                ...$check->toArray(),
            ],
        ]);
    }

    /**
     * An address with no servers on it still succeeds, and Steam says so in a
     * `message` it sends only then.
     */
    public static function serversAtAddress(GameServerFactory ...$servers): MockResponse
    {
        $payload = [
            'success' => true,
            'servers' => array_map(
                static fn (GameServerFactory $server): array => $server->toArray(),
                $servers,
            ),
        ];

        if ($servers === []) {
            $payload['message'] = 'No servers found at that address';
        }

        return MockResponse::make([
            'response' => $payload,
        ]);
    }

    /**
     * The config sits at the top level, without the `response` wrapper, beside
     * the `success` flag Steam sends with it.
     */
    public static function sdrConfig(SdrConfigFactory $config): MockResponse
    {
        return MockResponse::make([
            ...$config->toArray(),
            'success' => true,
        ]);
    }

    /**
     * `count` is Steam's own total for the filter, so it is independent of how
     * many items the payload lists — see {@see AppNewsFactory::total()}.
     */
    public static function appNews(AppNewsFactory $news): MockResponse
    {
        return MockResponse::make([
            'appnews' => $news->toArray(),
        ]);
    }

    /**
     * A whole feed, paged the way Steam pages it: up to `count` items, 20 when the
     * request sends none, dated on or before `enddate`, under a `count` of how many
     * remain. Pass them newest first with their own `id()`; `feeds`, `tags` and
     * `maxlength` are not applied.
     *
     * @return Closure(PendingRequest): MockResponse
     */
    public static function newsFeed(NewsItemFactory ...$items): Closure
    {
        return static function (PendingRequest $pendingRequest) use ($items): MockResponse {
            $request = $pendingRequest->getRequest();

            if (! $request instanceof GetNewsForAppRequest) {
                throw new LogicException('A faked news feed answers GetNewsForAppRequest only.');
            }

            $remaining = array_filter(
                $items,
                static fn (NewsItemFactory $item): bool => ! $request->endDate instanceof DateTimeInterface
                    || $item->make()->publishedAt->getTimestamp() <= $request->endDate->getTimestamp(),
            );

            return self::appNews(
                AppNewsFactory::new()
                    ->appId($request->appId)
                    ->items(...array_slice($remaining, 0, $request->count ?? 20))
                    ->total(count($remaining)),
            );
        };
    }

    /**
     * `servertimestring` is the same moment in US Pacific time without an offset,
     * which nothing reads; it is here because Steam sends it.
     */
    public static function serverInfo(DateTimeInterface $time): MockResponse
    {
        $pacific = DateTimeImmutable::createFromInterface($time)->setTimezone(new DateTimeZone('America/Los_Angeles'));

        return MockResponse::make([
            'servertime' => $time->getTimestamp(),
            'servertimestring' => sprintf('%s %2d %s', $pacific->format('D M'), $pacific->format('j'), $pacific->format('H:i:s Y')),
        ]);
    }

    public static function supportedApiList(WebApiInterfaceFactory ...$interfaces): MockResponse
    {
        return MockResponse::make([
            'apilist' => [
                'interfaces' => array_map(
                    static fn (WebApiInterfaceFactory $interface): array => $interface->toArray(),
                    $interfaces,
                ),
            ],
        ]);
    }

    /**
     * A hidden friend list, which the connector raises from the bare 401 as
     * `ProfileNotPublicException`.
     */
    public static function friendListNotPublic(): MockResponse
    {
        return MockResponse::make('{}', 401);
    }

    /**
     * A hidden group list, which the connector raises from the 403 as
     * `ProfileNotPublicException`.
     */
    public static function userGroupListNotPublic(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => false,
                'error' => 'Private profile',
            ],
        ], 403);
    }

    /**
     * Every IPlayerService endpoint answers a hidden profile with 200 and an empty
     * `response` object, dropping only the key it reads — `game_count`, `total_count`,
     * `player_level`. Nothing but that absence separates it from a player who owns or
     * played nothing, so this cannot be expressed as a status code.
     */
    public static function playerServiceNotPublic(): MockResponse
    {
        return MockResponse::make(['response' => (object) []]);
    }

    /**
     * A hidden profile `GetUserStatsForGame` answers with 400 and an empty JSON object,
     * raised as `ProfileNotPublicException`.
     */
    public static function statsRefused(): MockResponse
    {
        return MockResponse::make('{}', 400);
    }

    /**
     * `GetPlayerAchievements` answers 400 with this body for an app without stats and for
     * a hidden profile alike, raising `StatsUnavailableException` either way.
     */
    public static function playerAchievementsRefused(): MockResponse
    {
        return MockResponse::make([
            'playerstats' => [
                'error' => 'Requested app has no stats',
                'success' => false,
            ],
        ], 400);
    }

    /**
     * An app ID Steam does not know, which `GetNumberOfCurrentPlayers` raises as
     * `AppNotFoundException`. The request reads the status alone; `result: 42` is
     * here because that is what Steam sends with it.
     */
    public static function appNotFound(): MockResponse
    {
        return MockResponse::make(['response' => ['result' => 42]], 404);
    }

    /**
     * `GetGlobalAchievementPercentagesForApp` answers 403 with an empty JSON object
     * for a game carrying no achievements and for a game ID it does not know alike,
     * raising `StatsUnavailableException` either way. The request claims this status
     * before the connector can read it as a rejected key or a hidden profile.
     */
    public static function globalAchievementsRefused(): MockResponse
    {
        return MockResponse::make('{}', 403);
    }

    /**
     * An app ID `GetSchemaForGame` does not know, which it raises as
     * `AppNotFoundException`. Byte for byte what {@see self::statsRefused()} returns,
     * because Steam answers both with a bare 400 — the two keep their own names since
     * nothing but the endpoint says which failure a test is faking.
     */
    public static function schemaAppNotFound(): MockResponse
    {
        return MockResponse::make('{}', 400);
    }

    /**
     * `ResolveVanityURL` reports an unclaimed slug in the body, not the status.
     */
    public static function vanityNotFound(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => 42,
                'message' => 'No match',
            ],
        ]);
    }

    /**
     * `UpToDateCheck` answers 200 with `success: false` alike for an app ID Steam
     * does not know and for an app running no versioned servers, raising
     * `AppVersionUnavailableException` either way.
     */
    public static function appVersionUnavailable(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => false,
                'error' => "Couldn't get app info for the app specified.",
            ],
        ]);
    }

    /**
     * An address `GetServersAtAddress` rejects, raising `InvalidServerAddressException`.
     * The request tells it apart from {@see self::serversAtAddressRefused()} by the
     * `'addr' param` in the message alone.
     */
    public static function invalidServerAddress(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => false,
                'message' => "'addr' param should specify a valid IPv4 or IPv4:queryport",
            ],
        ]);
    }

    /**
     * An address `GetServersAtAddress` refuses to look up at all. The SDK raises
     * it as the root `SteamApiException`, so nothing in the type names this failure.
     */
    public static function serversAtAddressRefused(): MockResponse
    {
        return MockResponse::make([
            'response' => [
                'success' => false,
                'message' => "Please don't call this API more often than once per minute for a given IP.",
            ],
        ]);
    }

    /**
     * An app ID `GetSDRConfig` does not know, the third shape of it after
     * {@see self::appNotFound()} and {@see self::schemaAppNotFound()}. The request
     * raises `AppNotFoundException` before the connector can retry the 500.
     */
    public static function sdrConfigAppNotFound(): MockResponse
    {
        return MockResponse::make([
            'success' => false,
            'message' => 'Failed to get appinfo',
        ], 500);
    }

    /**
     * `GetNewsForApp` answers 403 with an empty JSON object for an app ID it does
     * not know and for some apps that exist, such as Spacewar, raising
     * `AppNewsUnavailableException` either way. The request claims this status
     * before the connector can read it as a rejected key or a hidden profile.
     */
    public static function appNewsUnavailable(): MockResponse
    {
        return MockResponse::make('{}', 403);
    }

    /**
     * A key `GetSupportedAPIList` rejects, answered with a 403 and an empty list rather
     * than the `key=` page {@see self::invalidApiKey()} returns. The request raises
     * `InvalidApiKeyException` itself before the connector can read it as a hidden profile.
     */
    public static function supportedApiListInvalidKey(): MockResponse
    {
        return MockResponse::make(['apilist' => ['interfaces' => []]], 403);
    }

    /**
     * A key Steam rejects. The connector matches on `key=` in the body, so the
     * echoed query string is what makes this an `InvalidApiKeyException`.
     */
    public static function invalidApiKey(): MockResponse
    {
        return MockResponse::make(
            <<<'HTML'
                <html>
                    <head>
                        <title>Forbidden</title>
                    </head>
                    <body>
                        <h1>Forbidden</h1>
                        Access is denied. Retrying will not help. Please verify your <pre>key=</pre>
                        parameter.
                    </body>
                </html>
                HTML,
            403,
        );
    }

    /**
     * The same rejected key on 401 instead of 403. Which status a key error
     * lands on is the endpoint's choice — `GetCommunityBadgeProgress` picks
     * this one, and without the `key=` marker it would read as a hidden profile.
     */
    public static function apiKeyUnauthorized(): MockResponse
    {
        return MockResponse::make(
            <<<'HTML'
                <html>
                    <head>
                        <title>Unauthorized</title>
                    </head>
                    <body>
                        <h1>Unauthorized</h1>
                        Access is denied. Retrying will not help. Please verify your <pre>key=</pre>
                        parameter.
                    </body>
                </html>
                HTML,
            401,
        );
    }

    /**
     * A request that reached Steam without a key at all. Steam answers in HTML
     * rather than JSON, which is how the connector tells it apart from a real
     * API error on the same status.
     */
    public static function apiKeyMissing(): MockResponse
    {
        return MockResponse::make(
            <<<'HTML'
                <html>
                    <head>
                        <title>Bad Request</title>
                    </head>
                    <body>
                        <h1>Bad Request</h1>
                        Required parameter 'key' is missing
                    </body>
                </html>
                HTML,
            400,
        );
    }

    /**
     * A request that never reaches Steam, retried like a real outage and raised as
     * `SteamConnectionException` once the tries are spent. Saloon throws it before
     * recording a response, so `getRecordedResponses()` and the `assertSent` family
     * never see the attempt.
     */
    public static function connectionFailed(): MockResponse
    {
        return MockResponse::make()->throw(
            static fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(
                new ConnectException(
                    'cURL error 28: Operation timed out after 10000 milliseconds',
                    $pendingRequest->createPsrRequest(),
                ),
                $pendingRequest,
            ),
        );
    }

    /**
     * Steam throttling the key, raised as `SteamRateLimitException`. The lock it leaves
     * refuses later requests sent the same way for `Retry-After` seconds, or
     * `tooManyRequestsCooldown` without one.
     */
    public static function tooManyRequests(?int $retryAfter = null): MockResponse
    {
        return MockResponse::make([], 429, $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter]);
    }
}
