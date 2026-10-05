<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;

covers([SteamApiException::class, SteamConnectionException::class, SteamConnector::class]);

/**
 * Guzzle 8 drops the query from this message; the closure replays Guzzle 7, which quotes it whole.
 */
function timeoutQuotingUri(): Closure
{
    return static fn (RequestInterface $request): ConnectException => new ConnectException(
        sprintf('cURL error 28: Operation timed out after 5000 milliseconds (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for %s', $request->getUri()),
        $request,
    );
}

function failureQuotingUri(int $status): Closure
{
    return static function (RequestInterface $request) use ($status): ClientException|ServerException {
        $response = new PsrResponse($status);
        $summary = sprintf('`GET %s` resulted in a `%d %s` response', $request->getUri(), $status, $response->getReasonPhrase());

        return $status >= 500
            ? new ServerException('Server error: '.$summary, $request, $response)
            : new ClientException('Client error: '.$summary, $request, $response);
    };
}

function failureOf(Closure $call): Throwable
{
    try {
        $call();
    } catch (Throwable $throwable) {
        return $throwable;
    }

    throw new RuntimeException('Expected the request to fail.');
}

function chainMessages(Throwable $throwable): string
{
    $messages = [];

    for ($link = $throwable; $link instanceof Throwable; $link = $link->getPrevious()) {
        $messages[] = $link->getMessage();
    }

    return implode("\n", $messages);
}

test('a connection failure masks the key in the URI Guzzle quoted', function (): void {
    $connector = connectorAnswering([timeoutQuotingUri()], new SteamConfig('secret-key'));

    $thrown = failureOf(fn (): mixed => $connector->send(friendListRequest()));

    expect($thrown)->toBeInstanceOf(SteamConnectionException::class)
        ->and($thrown->getMessage())->toBe(
            'Could not reach the Steam Web API: cURL error 28: Operation timed out after 5000 milliseconds '
            .'(see https://curl.haxx.se/libcurl/c/libcurl-errors.html) '
            .'for https://api.steampowered.com/ISteamUser/GetFriendList/v1/?key=***&steamid=76561198148125221',
        )
        ->and($thrown->getPrevious())->toBeNull();
});

test('no message in the chain of a connection failure carries the key', function (Closure $call): void {
    expect(chainMessages(failureOf($call)))->not->toContain('secret-key');
})->with([
    'send' => [static fn (): mixed => connectorAnswering([timeoutQuotingUri()], new SteamConfig('secret-key'))
        ->send(friendListRequest())],
    'sendAsync' => [static fn (): mixed => connectorAnswering([timeoutQuotingUri()], new SteamConfig('secret-key'))
        ->sendAsync(friendListRequest())
        ->wait()],
    'send, retried' => [static fn (): mixed => connectorAnswering([timeoutQuotingUri(), timeoutQuotingUri()], new SteamConfig('secret-key', tries: 2))
        ->send(friendListRequest())],
]);

test('a request Steam serves anonymously keeps the quoted URI whole', function (): void {
    $connector = connectorAnswering([timeoutQuotingUri()], new SteamConfig('secret-key'));

    $thrown = failureOf(fn (): mixed => $connector->send(new GetNumberOfCurrentPlayersRequest(440)));

    expect($thrown)->toBeInstanceOf(SteamConnectionException::class)
        ->and($thrown->getMessage())
        ->toEndWith('for https://api.steampowered.com/ISteamUserStats/GetNumberOfCurrentPlayers/v1/?appid=440');
});

test('a 4xx or 5xx chains nothing under SteamApiException', function (int $status): void {
    $connector = connectorAnswering([failureQuotingUri($status)], new SteamConfig('secret-key'));

    $thrown = failureOf(fn (): mixed => $connector->send(friendListRequest()));

    expect($thrown::class)->toBe(SteamApiException::class)
        ->and($thrown->getMessage())->toBe(sprintf('Steam API request failed with HTTP %d.', $status))
        ->and($thrown->getCode())->toBe($status)
        ->and($thrown->getPrevious())->toBeNull();
})->with([404, 503]);

test('no message in the chain of a 4xx or 5xx carries the key', function (Closure $call): void {
    expect(chainMessages(failureOf($call)))->not->toContain('secret-key');
})->with([
    'send' => [static fn (): mixed => connectorAnswering([failureQuotingUri(503)], new SteamConfig('secret-key'))
        ->send(friendListRequest())],
    'sendAsync' => [static fn (): mixed => connectorAnswering([failureQuotingUri(503)], new SteamConfig('secret-key'))
        ->sendAsync(friendListRequest())
        ->wait()],
]);
