<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Senders\GuzzleSender;

covers([SteamConnectionException::class, SteamConnector::class]);

/**
 * Saloon's MockResponse cannot raise a transport failure, so the queue is handed to
 * Guzzle's own handler underneath the sender instead.
 *
 * @param  list<PsrResponse|Throwable>  $queue
 */
function connectorAnswering(array $queue): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig('any'));
    $sender = $connector->sender();

    assert($sender instanceof GuzzleSender);

    $sender->getHandlerStack()->setHandler(new MockHandler($queue));

    return $connector;
}

function connectionFailure(string $message = 'cURL error 28: Operation timed out after 5000 milliseconds'): ConnectException
{
    return new ConnectException($message, new PsrRequest('GET', 'https://api.steampowered.com'));
}

function friendListRequest(): GetFriendListRequest
{
    return new GetFriendListRequest(SteamId::fromSteamId64('76561198148125221'));
}

test('a request that never reaches Steam raises SteamConnectionException', function (): void {
    $connector = connectorAnswering([connectionFailure()]);

    expect(fn (): mixed => $connector->send(friendListRequest()))
        ->toThrow(
            SteamConnectionException::class,
            'Could not reach the Steam Web API: cURL error 28: Operation timed out after 5000 milliseconds',
        );
});

test('a connection failure carries no response, no status and the Saloon exception', function (): void {
    $connector = connectorAnswering([connectionFailure()]);

    try {
        $connector->send(friendListRequest());
    } catch (SteamConnectionException $steamConnectionException) {
        expect($steamConnectionException)->toBeInstanceOf(SteamApiException::class)
            ->and($steamConnectionException->response)->toBeNull()
            ->and($steamConnectionException->getCode())->toBe(0)
            ->and($steamConnectionException->getPrevious())->toBeInstanceOf(FatalRequestException::class)
            ->and($steamConnectionException->getPrevious()?->getPrevious())->toBeInstanceOf(ConnectException::class);

        return;
    }

    throw new RuntimeException('Expected the request to fail.');
});

test('a network failure Saloon lets through on send raises SteamConnectionException', function (): void {
    // Stands in for Guzzle 8's NetworkTimeoutException, which Guzzle 7 does not ship.
    $networkFailure = new class('cURL error 28: Operation timed out after 30000 milliseconds') extends RuntimeException implements NetworkExceptionInterface
    {
        public function getRequest(): RequestInterface
        {
            return new PsrRequest('GET', 'https://api.steampowered.com');
        }
    };

    $connector = connectorAnswering([$networkFailure]);

    try {
        $connector->send(friendListRequest());
    } catch (SteamConnectionException $steamConnectionException) {
        expect($steamConnectionException->getMessage())
            ->toBe('Could not reach the Steam Web API: cURL error 28: Operation timed out after 30000 milliseconds')
            ->and($steamConnectionException->response)->toBeNull()
            ->and($steamConnectionException->getPrevious())->toBe($networkFailure);

        return;
    }

    throw new RuntimeException('Expected the request to fail.');
});

test('sendAsync rejects a connection failure with SteamConnectionException', function (): void {
    $connector = connectorAnswering([connectionFailure('cURL error 6: Could not resolve host')]);

    expect(fn (): mixed => $connector->sendAsync(friendListRequest())->wait())
        ->toThrow(
            SteamConnectionException::class,
            'Could not reach the Steam Web API: cURL error 6: Could not resolve host',
        );
});

test('sendAsync leaves a failure Steam did answer mapped as it was', function (): void {
    $connector = connectorAnswering([new PsrResponse(500, [], '<html>Server Error</html>')]);

    try {
        $connector->sendAsync(friendListRequest())->wait();
    } catch (SteamApiException $steamApiException) {
        expect($steamApiException::class)->toBe(SteamApiException::class)
            ->and($steamApiException->getMessage())->toBe('Steam API request failed with HTTP 500.')
            ->and($steamApiException->getCode())->toBe(500);

        return;
    }

    throw new RuntimeException('Expected the request to fail.');
});

test('sendAsync leaves a failure raised before the request went out untouched', function (): void {
    $connector = new SteamConnector(new SteamConfig);

    expect(fn (): mixed => $connector->sendAsync(friendListRequest())->wait())
        ->toThrow(ApiKeyNotConfiguredException::class);
});

test('pool reports a failure Steam did answer as SteamApiException', function (): void {
    $connector = connectorAnswering([new PsrResponse(502, [], 'Bad Gateway')]);

    $thrown = null;

    $pool = $connector->pool([friendListRequest()]);
    $pool->withExceptionHandler(function (mixed $reason) use (&$thrown): void {
        $thrown = $reason;
    });
    $pool->send()->wait();

    expect($thrown)->toBeInstanceOf(SteamApiException::class)
        ->and($thrown?->getMessage())->toBe('Steam API request failed with HTTP 502.');
});

test('pool reports a connection failure as SteamConnectionException', function (): void {
    $connector = connectorAnswering([connectionFailure()]);

    $thrown = null;

    $pool = $connector->pool([friendListRequest()]);
    $pool->withExceptionHandler(function (mixed $reason) use (&$thrown): void {
        $thrown = $reason;
    });
    $pool->send()->wait();

    expect($thrown)->toBeInstanceOf(SteamConnectionException::class);
});
