<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Hooks\RequestSending;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetSchemaForGameRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Senders\GuzzleSender;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([SteamConnector::class, RequestSending::class, ResponseReceived::class]);

beforeEach(function (): void {
    MemoryStore::clear();
});

afterEach(function (): void {
    MemoryStore::clear();
});

/**
 * @param  list<RequestSending>  $sent
 * @param  list<ResponseReceived>  $received
 */
function hooked(SteamConnector $connector, array &$sent, array &$received): SteamConnector
{
    return $connector
        ->onRequest(static function (RequestSending $sending) use (&$sent): void {
            $sent[] = $sending;
        })
        ->onResponse(static function (ResponseReceived $responseReceived) use (&$received): void {
            $received[] = $responseReceived;
        });
}

/**
 * @param  list<ResponseReceived>  $received
 * @return list<array{int, int}>
 */
function attemptsAndStatuses(array $received): array
{
    return array_map(
        static fn (ResponseReceived $responseReceived): array => [$responseReceived->attempt, $responseReceived->status],
        $received,
    );
}

/**
 * @param  list<array<string, mixed>>  $history
 */
function recordingWire(SteamConnector $connector, array &$history): SteamConnector
{
    $sender = $connector->sender();

    assert($sender instanceof GuzzleSender);

    $sender->getHandlerStack()->push(Middleware::history($history));

    return $connector;
}

function emptyAnswer(): PsrResponse
{
    return new PsrResponse(200, [], '{}');
}

test('onRequest gets the Steam method, the query without the key and the attempt', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering([emptyAnswer()], new SteamConfig('secret-key', language: Language::Polish)), $sent, $received);

    $connector->send(new GetSchemaForGameRequest(440));

    expect($sent)->toEqual([new RequestSending('ISteamUserStats/GetSchemaForGame/v2', ['appid' => 440, 'l' => 'polish'], 1)]);
});

test('onResponse gets the status and how long Steam took', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering([static function (): PsrResponse {
        usleep(20_000);

        return new PsrResponse(204);
    }]), $sent, $received);

    $connector->onRequest(static function (): void {
        usleep(200_000);
    });

    $connector->send(friendListRequest());

    expect($received)->toHaveCount(1)
        ->and($received[0]->method)->toBe('ISteamUser/GetFriendList/v1')
        ->and($received[0]->query)->toBe(['steamid' => '76561198148125221'])
        ->and($received[0]->attempt)->toBe(1)
        ->and($received[0]->status)->toBe(204)
        ->and($received[0]->duration)->toBeGreaterThanOrEqual(0.02)->toBeLessThan(0.2);
});

test('every attempt fires both hooks with its number', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering(
        [new PsrResponse(503), new PsrResponse(503), emptyAnswer()],
        new SteamConfig('any', tries: 3),
    ), $sent, $received);

    $connector->send(friendListRequest());

    expect(array_column($sent, 'attempt'))->toBe([1, 2, 3])
        ->and(attemptsAndStatuses($received))->toBe([[1, 503], [2, 503], [3, 200]]);
});

test('a try Steam never answered fires onRequest alone', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering(
        [connectionFailure(), emptyAnswer()],
        new SteamConfig('any', tries: 2),
    ), $sent, $received);

    $connector->send(friendListRequest());

    expect(array_column($sent, 'attempt'))->toBe([1, 2])
        ->and(attemptsAndStatuses($received))->toBe([[2, 200]]);
});

test('a request sent again counts from one', function (): void {
    $sent = [];
    $received = [];
    $request = friendListRequest();
    $connector = hooked(connectorAnswering([emptyAnswer(), emptyAnswer()]), $sent, $received);

    $connector->send($request);
    $connector->sendAsync($request)->wait();

    expect(array_column($sent, 'attempt'))->toBe([1, 1]);
});

test('a 429 reaches onResponse before the rate limiter raises', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering(
        [new PsrResponse(429, ['Retry-After' => '120'])],
        new SteamConfig('test-key'),
    ), $sent, $received);

    expect(fn (): mixed => $connector->send(friendListRequest()))->toThrow(SteamRateLimitException::class)
        ->and(attemptsAndStatuses($received))->toBe([[1, 429]]);
});

test('a request refused locally fires no hook', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering(
        [new PsrResponse(429, ['Retry-After' => '120'])],
        new SteamConfig('test-key'),
    ), $sent, $received);

    expect(fn (): mixed => $connector->send(friendListRequest()))->toThrow(SteamRateLimitException::class)
        ->and(fn (): mixed => $connector->send(friendListRequest()))->toThrow(SteamRateLimitException::class)
        ->and($sent)->toHaveCount(1)
        ->and($received)->toHaveCount(1);
});

test('onRequest sees the query a middleware added', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering([emptyAnswer()]), $sent, $received);

    $connector->middleware()->onRequest(static function (PendingRequest $pendingRequest): void {
        $pendingRequest->query()->add('format', 'json');
    });

    $connector->send(friendListRequest());

    expect($sent[0]->query)->toBe(['steamid' => '76561198148125221', 'format' => 'json']);
});

test('hooks fire under sendAsync and pool with attempt one', function (): void {
    $sent = [];
    $received = [];
    $connector = hooked(connectorAnswering(
        [emptyAnswer(), new PsrResponse(503)],
        new SteamConfig('any', tries: 3),
    ), $sent, $received);

    $connector->pool(
        [friendListRequest(), friendListRequest()],
        concurrency: 1,
        exceptionHandler: static function (): void {},
    )->send()->wait();

    expect(array_column($sent, 'attempt'))->toBe([1, 1])
        ->and(attemptsAndStatuses($received))->toBe([[1, 200], [1, 503]]);
});

test('every registered hook fires in order', function (): void {
    $calls = [];
    $connector = connectorAnswering([emptyAnswer()]);

    expect($connector->onRequest(static function () use (&$calls): void {
        $calls[] = 'request 1';
    }))->toBe($connector)
        ->and($connector->onResponse(static function () use (&$calls): void {
            $calls[] = 'response 1';
        }))->toBe($connector);

    $connector
        ->onRequest(static function () use (&$calls): void {
            $calls[] = 'request 2';
        })
        ->onResponse(static function () use (&$calls): void {
            $calls[] = 'response 2';
        })
        ->send(friendListRequest());

    expect($calls)->toBe(['request 1', 'request 2', 'response 1', 'response 2']);
});

test('a throwing hook reaches the caller', function (Closure $register, Closure $send): void {
    $failure = new RuntimeException('Hook failed.');
    $connector = $register(connectorAnswering([emptyAnswer()]), static fn (): never => throw $failure);

    try {
        $send($connector);
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException)->toBe($failure);

        return;
    }

    throw new LogicException('Expected the hook to fail the call.');
})->with([
    'onRequest' => [static fn (SteamConnector $connector, Closure $hook): SteamConnector => $connector->onRequest($hook)],
    'onResponse' => [static fn (SteamConnector $connector, Closure $hook): SteamConnector => $connector->onResponse($hook)],
])->with([
    'send' => [static fn (SteamConnector $connector): mixed => $connector->send(friendListRequest())],
    'sendAsync' => [static fn (SteamConnector $connector): mixed => $connector->sendAsync(friendListRequest())->wait()],
]);

test('a throwing onRequest hook keeps the request from Steam', function (): void {
    $history = [];
    $connector = recordingWire(connectorAnswering([emptyAnswer()]), $history)
        ->onRequest(static fn (): never => throw new RuntimeException('Hook failed.'));

    expect(fn (): mixed => $connector->send(friendListRequest()))->toThrow(RuntimeException::class, 'Hook failed.')
        ->and($history)->toBeEmpty();
});

test('a throwing hook stops the retries', function (): void {
    $history = [];
    $connector = recordingWire(connectorAnswering(
        [new PsrResponse(503), new PsrResponse(503), new PsrResponse(503)],
        new SteamConfig('any', tries: 3),
    ), $history)->onResponse(static fn (): never => throw new RuntimeException('Hook failed.'));

    expect(fn (): mixed => $connector->send(friendListRequest()))->toThrow(RuntimeException::class, 'Hook failed.')
        ->and($history)->toHaveCount(1);
});
