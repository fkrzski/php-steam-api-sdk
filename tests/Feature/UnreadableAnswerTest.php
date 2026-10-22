<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\UnreadableResponseException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetBadgesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetRecentlyPlayedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetSteamLevelRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
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
use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Utils;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

covers([SteamApiResponse::class, UnreadableResponseException::class, SteamConnector::class]);

/**
 * @param  list<Throwable>  $failures
 */
function connectorRecordingFailures(SteamConnector $connector, array &$failures): SteamConnector
{
    return $connector->onFailure(static function (Throwable $throwable) use (&$failures): void {
        $failures[] = $throwable;
    });
}

function unreadableFailureOf(Closure $call): UnreadableResponseException
{
    $failure = failureFrom($call);

    return $failure instanceof UnreadableResponseException
        ? $failure
        : throw new LogicException(sprintf('Expected UnreadableResponseException, got %s.', $failure::class), previous: $failure);
}

function requestAskingFor(?string $format): Request
{
    return new class($format) extends Request
    {
        protected Method $method = Method::GET;

        public function __construct(
            private readonly ?string $format,
        ) {}

        public function resolveEndpoint(): string
        {
            return '/ISteamDirectory/GetCMList/v1/';
        }

        protected function defaultQuery(): array
        {
            return $this->format === null ? [] : ['format' => $this->format];
        }
    };
}

dataset('every SDK request', [
    'GetOwnedGames' => [new GetOwnedGamesRequest(steamId()), 'GetOwnedGames'],
    'GetRecentlyPlayedGames' => [new GetRecentlyPlayedGamesRequest(steamId()), 'GetRecentlyPlayedGames'],
    'GetSteamLevel' => [new GetSteamLevelRequest(steamId()), 'GetSteamLevel'],
    'GetBadges' => [new GetBadgesRequest(steamId()), 'GetBadges'],
    'GetCommunityBadgeProgress' => [new GetCommunityBadgeProgressRequest(steamId()), 'GetCommunityBadgeProgress'],
    'GetPlayerSummaries' => [new GetPlayerSummariesRequest([steamId()]), 'GetPlayerSummaries'],
    'GetPlayerBans' => [new GetPlayerBansRequest([steamId()]), 'GetPlayerBans'],
    'GetFriendList' => [friendListRequest(), 'GetFriendList'],
    'GetUserGroupList' => [new GetUserGroupListRequest(steamId()), 'GetUserGroupList'],
    'ResolveVanityURL' => [new ResolveVanityUrlRequest('gabelogannewell'), 'ResolveVanityURL'],
    'GetPlayerAchievements' => [new GetPlayerAchievementsRequest(steamId(), 440), 'GetPlayerAchievements'],
    'GetNumberOfCurrentPlayers' => [new GetNumberOfCurrentPlayersRequest(440), 'GetNumberOfCurrentPlayers'],
    'GetGlobalAchievementPercentagesForApp' => [new GetGlobalAchievementPercentagesForAppRequest(440), 'GetGlobalAchievementPercentagesForApp'],
    'GetSchemaForGame' => [new GetSchemaForGameRequest(440), 'GetSchemaForGame'],
    'GetUserStatsForGame' => [new GetUserStatsForGameRequest(steamId(), 440), 'GetUserStatsForGame'],
    'GetSDRConfig' => [new GetSdrConfigRequest(440), 'GetSDRConfig'],
    'GetServersAtAddress' => [new GetServersAtAddressRequest('1.2.3.4'), 'GetServersAtAddress'],
    'UpToDateCheck' => [new UpToDateCheckRequest(440, 1), 'UpToDateCheck'],
    'GetNewsForApp' => [new GetNewsForAppRequest(440), 'GetNewsForApp'],
    'GetServerInfo' => [new GetServerInfoRequest, 'GetServerInfo'],
    'GetSupportedAPIList' => [new GetSupportedApiListRequest, 'GetSupportedAPIList'],
]);

dataset('bodies that are not JSON', [
    'an HTML page' => ['<html>maintenance</html>'],
    'a JSON string' => ['"maintenance"'],
    'JSON null' => ['null'],
]);

test('a 2xx body that is not JSON fails the call and reaches onFailure once', function (Request $request, string $method, string $body, Closure $send): void {
    $mockClient = new MockClient([MockResponse::make($body)]);
    $failures = [];
    $connector = connectorRecordingFailures(fakedConnector($mockClient), $failures);

    $exception = unreadableFailureOf(static fn (): mixed => $send($connector, $request));

    expect($exception->getMessage())->toBe(sprintf('%s: Steam API answered HTTP 200 with a body that is not JSON.', $method))
        ->and($exception->getCode())->toBe(200)
        ->and($exception->response)->toBe($mockClient->getLastResponse())
        ->and($failures)->toBe([$exception]);
})->with('every SDK request')->with('bodies that are not JSON')->with(everyWayToSend());

test('a body that is not JSON fails the same through Guzzle', function (Request $request, string $method, Closure $send): void {
    $failures = [];
    $connector = connectorRecordingFailures(connectorAnswering([new PsrResponse(200, [], '<html>maintenance</html>')]), $failures);

    $exception = unreadableFailureOf(static fn (): mixed => $send($connector, $request));

    expect($exception->getMessage())->toBe(sprintf('%s: Steam API answered HTTP 200 with a body that is not JSON.', $method))
        ->and($failures)->toBe([$exception]);
})->with([
    'GetOwnedGames' => [new GetOwnedGamesRequest(steamId()), 'GetOwnedGames'],
    'GetFriendList' => [friendListRequest(), 'GetFriendList'],
])->with(everyWayToSend());

test('a resource call raises it before building a result', function (Closure $call, string $method): void {
    $failures = [];
    $connector = connectorRecordingFailures(fakedConnector(new MockClient([MockResponse::make('<html>maintenance</html>')])), $failures);

    $exception = unreadableFailureOf(static fn (): mixed => $call($connector));

    expect($exception->getMessage())->toBe(sprintf('%s: Steam API answered HTTP 200 with a body that is not JSON.', $method))
        ->and($failures)->toBe([$exception]);
})->with([
    'players()->ownedGames()' => [static fn (SteamConnector $connector): array => $connector->players()->ownedGames(steamId()), 'GetOwnedGames'],
    'users()->friends()' => [static fn (SteamConnector $connector): array => $connector->users()->friends(steamId()), 'GetFriendList'],
]);

test('an unreadable answer is not retried', function (): void {
    $mockClient = new MockClient(array_fill(0, 3, MockResponse::make('<html>maintenance</html>')));

    unreadableFailureOf(static fn (): Response => fakedConnector($mockClient, new SteamConfig('test-key', tries: 3))->send(friendListRequest()));

    $mockClient->assertSentCount(1);
});

test('an answer on a stream that cannot rewind is read once', function (): void {
    $body = new NoSeekStream(Utils::streamFor('{"friendslist":{"friends":[{"steamid":"76561198000000001","relationship":"friend","friend_since":1750068880}]}}'));

    $friends = connectorAnswering([new PsrResponse(200, [], $body)])->users()->friends(steamId());

    expect($friends)->toHaveCount(1)
        ->and($friends[0]->steamId->value)->toBe('76561198000000001');
});

test('an empty 2xx body reads as an empty JSON object', function (int $status): void {
    $response = fakedConnector(new MockClient([MockResponse::make('', $status)]))->send(requestAskingFor(null));

    expect($response->status())->toBe($status)
        ->and($response->json())->toBe([]);
})->with([200, 204]);

test('a request reading JSON fails on a body that is not', function (?string $format): void {
    $exception = unreadableFailureOf(static fn (): Response => fakedConnector(new MockClient([MockResponse::make('<html>maintenance</html>')]))->send(requestAskingFor($format)));

    expect($exception->getMessage())->toBe('GetCMList: Steam API answered HTTP 200 with a body that is not JSON.');
})->with([
    'no format' => [null],
    'format=json' => ['json'],
]);

test('a request asking for another format reads its own body', function (string $format, string $body): void {
    $response = fakedConnector(new MockClient([MockResponse::make($body)]))->send(requestAskingFor($format));

    expect($response->body())->toBe($body);
})->with([
    'xml' => ['xml', '<?xml version="1.0" encoding="UTF-8"?><response><serverlist></serverlist></response>'],
    'vdf' => ['vdf', '"response" { "serverlist" { } }'],
]);

test('an error status keeps its own exception whatever the body', function (MockResponse $answer, string $class, int $status): void {
    $exception = failureFrom(static fn (): Response => fakedConnector(new MockClient([$answer]))->send(friendListRequest()));

    expect($exception::class)->toBe($class)
        ->and($exception->getCode())->toBe($status);
})->with([
    'a key error in HTML' => [SteamResponse::apiKeyMissing(), InvalidApiKeyException::class, 400],
    'an HTML 5xx' => [MockResponse::make('<html>Server Error</html>', 503), SteamApiException::class, 503],
]);
