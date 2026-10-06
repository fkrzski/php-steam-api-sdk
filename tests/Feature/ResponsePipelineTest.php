<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([SteamConnector::class]);

beforeEach(function (): void {
    MemoryStore::clear();
});

dataset('answering 200s', [
    'MockClient' => [static fn (int $count): SteamConnector => new SteamConnector(new SteamConfig('any'))->withMockClient(new MockClient(
        array_fill(0, $count, MockResponse::make('{}')),
    ))],
    'Guzzle handler' => [static fn (int $count): SteamConnector => connectorAnswering(
        array_fill(0, $count, new PsrResponse(200, [], '{}')),
    )],
]);

test('a middleware throwing on a 200 fails an async call', function (Closure $answering): void {
    $failure = new RuntimeException('Rejected by a middleware.');
    $reason = null;
    $connector = $answering(1);

    $connector->middleware()->onResponse(static fn (): never => throw $failure);

    $connector->sendAsync(friendListRequest())
        ->otherwise(static function (mixed $rejection) use (&$reason): void {
            $reason = $rejection;
        })
        ->wait();

    expect($reason)->toBe($failure);
})->with('answering 200s');

test('a response a middleware swaps in reaches the async caller', function (Closure $answering): void {
    $swapped = null;
    $connector = $answering(1);

    $connector->middleware()->onResponse(static function (Response $response) use (&$swapped): Response {
        return $swapped = new Response(new PsrResponse(202), $response->getPendingRequest(), $response->getPsrRequest());
    });

    expect($connector->sendAsync(friendListRequest())->wait())->toBe($swapped);
})->with('answering 200s');

test('a middleware sees an async call as asynchronous', function (Closure $answering): void {
    $asynchronous = null;
    $connector = $answering(1);

    $connector->middleware()->onResponse(static function (Response $response) use (&$asynchronous): void {
        $asynchronous = $response->getPendingRequest()->isAsynchronous();
    });

    $connector->sendAsync(friendListRequest())->wait();

    expect($asynchronous)->toBeTrue();
})->with('answering 200s');

test("a pool hands a middleware's failure on a 200 to the exception handler", function (Closure $answering): void {
    $failure = new RuntimeException('Rejected by a middleware.');
    $reasons = [];
    $connector = $answering(2);

    $connector->middleware()->onResponse(static fn (): never => throw $failure);

    $connector->pool(
        [friendListRequest(), friendListRequest()],
        exceptionHandler: static function (mixed $reason) use (&$reasons): void {
            $reasons[] = $reason;
        },
    )->send()->wait();

    expect($reasons)->toBe([$failure, $failure]);
})->with('answering 200s');
