<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidSteamIdException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\StatsUnavailableException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
use Fkrzski\SteamApiSdk\SteamConfig;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Utils;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

covers([SteamApiRequest::class, SteamApiResponse::class]);

function answeringAnUnbuildableSteamId(): MockClient
{
    return new MockClient([MockResponse::make(['response' => ['success' => 1, 'steamid' => 'gabe']])]);
}

test('a result that cannot be built fails the call and reaches onFailure once', function (Closure $send): void {
    $failures = [];
    $connector = recordingFailures(fakedConnector(answeringAnUnbuildableSteamId()), $failures);

    $exception = failureFrom(static fn (): mixed => $send($connector, new ResolveVanityUrlRequest('gabelogannewell')));

    expect($exception::class)->toBe(InvalidSteamIdException::class)
        ->and($failures)->toBe([$exception]);
})->with(everyWayToSend());

test('a resource call raises it from the call, not after', function (): void {
    $failures = [];
    $connector = recordingFailures(fakedConnector(answeringAnUnbuildableSteamId()), $failures);

    $exception = failureFrom(static fn (): mixed => $connector->users()->resolveVanityUrl('gabelogannewell'));

    expect($exception::class)->toBe(InvalidSteamIdException::class)
        ->and($failures)->toBe([$exception]);
});

test('an answer on a stream that cannot rewind is decoded once', function (): void {
    $body = new NoSeekStream(Utils::streamFor('{"response":{"game_count":1,"games":[{"appid":440,"playtime_forever":5}]}}'));

    $games = connectorAnswering([new PsrResponse(200, [], $body)])->players()->ownedGames(steamId());

    expect($games)->toHaveCount(1)
        ->and($games[0]->appId)->toBe(440);
});

test('an error status the request reads is read once, off a stream that cannot rewind', function (Request $request, int $status, string $body, string $class): void {
    $history = [];
    $answer = static fn (): PsrResponse => new PsrResponse($status, [], new NoSeekStream(Utils::streamFor($body)));
    $connector = connectorAnswering([$answer(), $answer(), $answer()], new SteamConfig('any', tries: 3), $history);

    $exception = failureFrom(static fn (): Response => $connector->send($request));

    expect($exception::class)->toBe($class)
        ->and($history)->toHaveCount(1);
})->with([
    'GetSDRConfig, an unknown app' => [new GetSdrConfigRequest(440), 500, '{"success":false,"message":"Failed to get appinfo"}', AppNotFoundException::class],
    'GetPlayerAchievements, no stats' => [new GetPlayerAchievementsRequest(steamId(), 440), 400, '{"playerstats":{"error":"Requested app has no stats","success":false}}', StatsUnavailableException::class],
]);

test('an error status the request leaves to the connector builds no DTO', function (): void {
    $exception = failureFrom(static fn (): Response => fakedConnector(new MockClient([MockResponse::make('', 503)]))->send(new GetSdrConfigRequest(440)));

    expect($exception::class)->toBe(SteamApiException::class)
        ->and(failureFrom(static fn (): mixed => $exception instanceof SteamApiException ? $exception->response?->dto() : null)->getMessage())
        ->toBe('A failed answer builds no DTO.');
});

test('the result is the one built during the call', function (): void {
    $request = new GetSdrConfigRequest(440);
    $response = fakedConnector(new MockClient([MockResponse::fixture('ISteamApps/GetSDRConfig/default')]))->send($request);

    expect($response->dto())->toBe($request->createDtoFromResponse($response));
});

test('a failure read from the payload is the one dto() raises again', function (): void {
    $exception = failureFrom(static fn (): mixed => fakedConnector(new MockClient([MockResponse::fixture('IPlayerService/GetOwnedGames/private')]))->send(new GetOwnedGamesRequest(steamId())));

    expect($exception)->toBeInstanceOf(ProfileNotPublicException::class)
        ->and(failureFrom(static fn (): mixed => $exception instanceof SteamApiException ? $exception->response?->dto() : null))->toBe($exception);
});
