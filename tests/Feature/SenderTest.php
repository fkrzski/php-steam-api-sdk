<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Senders\SteamSender;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;
use Saloon\Contracts\Sender;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([SteamSender::class, SteamConnector::class]);

beforeEach(function (): void {
    MemoryStore::clear();
});

afterEach(function (): void {
    MemoryStore::clear();
});

function sendingThrough(Sender $sender, string $apiKey = 'any', int $tries = 1): SteamConnector
{
    return new SteamConnector(new SteamConfig($apiKey, tries: $tries, sender: $sender));
}

function noFriends(): PsrResponse
{
    return new PsrResponse(200, ['Content-Type' => 'application/json'], '{"friendslist":{"friends":[]}}');
}

function failureFrom(Closure $call): Throwable
{
    try {
        $call();
    } catch (Throwable $throwable) {
        return $throwable;
    }

    throw new LogicException('Expected the call to fail.');
}

/**
 * @return array<string, array{Closure(SteamConnector): mixed}>
 */
function everyWayToSend(): array
{
    return [
        'send' => [static fn (SteamConnector $connector): mixed => $connector->send(friendListRequest())],
        'sendAsync' => [static fn (SteamConnector $connector): mixed => $connector->sendAsync(friendListRequest())->wait()],
        'pool' => [static function (SteamConnector $connector): never {
            $reasons = [];

            $connector->pool(
                [friendListRequest()],
                exceptionHandler: static function (Throwable $reason) use (&$reasons): void {
                    $reasons[] = $reason;
                },
            )->send()->wait();

            throw $reasons[0] ?? new LogicException('Expected the pool to fail.');
        }],
    ];
}

test('the connector wraps its own Guzzle sender in SteamSender', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    expect($connector->sender())->toBeInstanceOf(SteamSender::class);
});

test('an injected sender carries every request', function (): void {
    $body = '{"friendslist":{"friends":[{"steamid":"76561197960265731","relationship":"friend","friend_since":0}]}}';
    $connector = sendingThrough(senderAnswering([new PsrResponse(200, [], $body)]));

    expect($connector->sender())->toBeInstanceOf(SteamSender::class)
        ->and($connector->send(friendListRequest())->body())->toBe($body);
});

test('a network failure from a sender beyond Guzzle is retried until Steam answers', function (): void {
    $connector = sendingThrough(senderAnswering([networkFailure(), noFriends()]), tries: 2);

    expect($connector->send(friendListRequest())->status())->toBe(200);
});

test('a network failure from a sender beyond Guzzle raises SteamConnectionException', function (Closure $send): void {
    $thrown = failureFrom(fn (): mixed => $send(sendingThrough(senderAnswering([networkFailure()]))));

    expect($thrown::class)->toBe(SteamConnectionException::class)
        ->and($thrown->getMessage())->toBe('GetFriendList: Could not reach the Steam Web API: cURL error 28: Operation timed out after 30000 milliseconds')
        ->and($thrown->getPrevious())->toBeNull();
})->with(everyWayToSend());

test('the key stays masked whatever sender failed', function (Closure $send): void {
    $connector = sendingThrough(senderAnswering([
        static fn (RequestInterface $request): Throwable => networkFailure(sprintf('Connection refused for %s', $request->getUri())),
    ]), 'secret-key');

    expect(failureFrom(fn (): mixed => $send($connector))->getMessage())->toBe(
        'GetFriendList: Could not reach the Steam Web API: Connection refused for '
        .'https://api.steampowered.com/ISteamUser/GetFriendList/v1/?key=***&steamid=76561198148125221',
    );
})->with(everyWayToSend());

test('a sender that resolves a failure gets the exception Guzzle raises', function (Closure $send, Closure $answer, string $expected): void {
    $fromGuzzle = failureFrom(fn (): mixed => $send(connectorAnswering([$answer()])));

    MemoryStore::clear();

    $responses = 0;
    $connector = sendingThrough(senderAnswering([$answer()]))->onResponse(static function () use (&$responses): void {
        $responses++;
    });
    $thrown = failureFrom(fn (): mixed => $send($connector));

    expect($fromGuzzle::class)->toBe($expected)
        ->and($thrown::class)->toBe($expected)
        ->and($thrown->getMessage())->toBe($fromGuzzle->getMessage())
        ->and($responses)->toBe(1);
})->with(everyWayToSend())->with([
    '500' => [static fn (): PsrResponse => new PsrResponse(500, [], '<html>Server Error</html>'), SteamApiException::class],
    '403 for the key' => [static fn (): PsrResponse => new PsrResponse(403, [], '<pre>key=</pre>'), InvalidApiKeyException::class],
    '429' => [static fn (): PsrResponse => new PsrResponse(429), SteamRateLimitException::class],
]);

test('a sender failure that is not a network one reaches the caller untouched', function (Closure $send): void {
    $failure = new LogicException('The sender broke.');
    $connector = sendingThrough(senderAnswering([$failure, noFriends()]), tries: 2);

    expect(failureFrom(fn (): mixed => $send($connector)))->toBe($failure);
})->with(everyWayToSend());
