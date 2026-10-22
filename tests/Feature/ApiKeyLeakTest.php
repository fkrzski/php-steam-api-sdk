<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Hooks\RequestSending;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
use Fkrzski\SteamApiSdk\Http\ApiKeyMask;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;
use Saloon\Http\PendingRequest;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Symfony\Component\VarDumper\VarDumper;

covers([ApiKeyMask::class, SteamConnector::class, SteamApiException::class, SteamConnectionException::class]);

afterEach(function (): void {
    VarDumper::setHandler(null);
});

/**
 * Its raw and URL-encoded forms differ, so masking one form misses the other.
 */
function leakableKey(): string
{
    return 'secret key+/';
}

function leakableConnector(PsrResponse|Closure $answer): SteamConnector
{
    return connectorAnswering([$answer], new SteamConfig(leakableKey()));
}

/**
 * Guzzle 7 quotes the URI as sent; the decoded copy stands in for a client quoting the key raw.
 */
function timeoutQuotingKey(): Closure
{
    return static fn (RequestInterface $request): ConnectException => new ConnectException(
        sprintf('cURL error 28: Operation timed out for %s (%s)', $request->getUri(), urldecode((string) $request->getUri())),
        $request,
    );
}

function everyMessageIn(Throwable $throwable): string
{
    $messages = [];

    for ($link = $throwable; $link instanceof Throwable; $link = $link->getPrevious()) {
        $messages[] = $link->getMessage();
    }

    return implode("\n", $messages);
}

test('no output carries the key in any form', function (Closure $output, string $form): void {
    expect($output())->not->toMatch($form);
})->with([
    'onRequest' => [static function (): string {
        $sent = [];

        leakableConnector(noFriends())
            ->onRequest(static function (RequestSending $sending) use (&$sent): void {
                $sent[] = $sending;
            })
            ->send(friendListRequest());

        return print_r($sent, true);
    }],
    'onResponse' => [static function (): string {
        $received = [];

        leakableConnector(noFriends())
            ->onResponse(static function (ResponseReceived $responseReceived) use (&$received): void {
                $received[] = $responseReceived;
            })
            ->send(friendListRequest());

        return print_r($received, true);
    }],
    'onFailure' => [static function (): string {
        $messages = [];
        $connector = leakableConnector(timeoutQuotingKey())->onFailure(static function (Throwable $throwable) use (&$messages): void {
            $messages[] = everyMessageIn($throwable);
        });

        failureFrom(fn (): mixed => $connector->send(friendListRequest()));

        return implode("\n", $messages);
    }],
    'debug()' => [static function (): string {
        $dumps = [];

        VarDumper::setHandler(static function (mixed $dump) use (&$dumps): void {
            $dumps[] = $dump;
        });

        leakableConnector(noFriends())->debug()->send(friendListRequest());

        return print_r($dumps, true);
    }],
    'debugRequest()' => [static function (): string {
        $requests = '';

        leakableConnector(noFriends())
            ->debugRequest(static function (PendingRequest $pendingRequest, RequestInterface $psrRequest) use (&$requests): void {
                $requests .= Message::toString($psrRequest);
            })
            ->send(friendListRequest());

        return $requests;
    }],
    'a connection failure' => [static function (): string {
        $connector = leakableConnector(timeoutQuotingKey());

        return everyMessageIn(failureFrom(fn (): mixed => $connector->send(friendListRequest())));
    }],
    'an HTTP error' => [static function (): string {
        $connector = leakableConnector(static fn (RequestInterface $request): ServerException => new ServerException(
            sprintf('Server error: `GET %s` resulted in a `503 Service Unavailable` response', $request->getUri()),
            $request,
            new PsrResponse(503),
        ));

        return everyMessageIn(failureFrom(fn (): mixed => $connector->send(friendListRequest())));
    }],
    'the rate limit store' => [static function (): string {
        leakableConnector(noFriends())->send(friendListRequest());

        return print_r((new MemoryStore)->getStore(), true);
    }],
])->with([
    'raw' => ['/'.preg_quote(leakableKey(), '/').'/'],
    'URL-encoded' => ['/'.preg_quote(urlencode(leakableKey()), '/').'/'],
    'as a query pair' => ['/key=(?!\*{3})/'],
]);
