<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Http\Call;
use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectionException;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;

covers([Call::class]);

function answerToACall(int $status = 200, ?Throwable $senderException = null, string $body = '{"friendslist":{"friends":[]}}'): Response
{
    $pendingRequest = new SteamConnector(new SteamConfig('any'))->createPendingRequest(friendListRequest());

    return SteamApiResponse::fromPsrResponse(new PsrResponse($status, [], $body), $pendingRequest, $pendingRequest->createPsrRequest(), $senderException);
}

/**
 * @param  list<Throwable>  $reported
 */
function callReporting(array &$reported, ?MockClient $mockClient = null): Call
{
    return new Call($mockClient, static function (Throwable $throwable) use (&$reported): void {
        $reported[] = $throwable;
    });
}

/**
 * @param  list<Response>  $piped
 */
function settledAsync(Call $call, mixed $outcome, array &$piped = []): mixed
{
    return $call->settleAsync(
        static fn (): PromiseInterface => $outcome instanceof Response ? Create::promiseFor($outcome) : Create::rejectionFor($outcome),
        static function (Response $response) use (&$piped): Response {
            $piped[] = $response;

            return $response;
        },
    )->wait();
}

dataset('failures a call maps', [
    'no answer' => [static fn (): Throwable => new FatalRequestException(connectionFailure(), answerToACall()->getPendingRequest()), SteamConnectionException::class],
    'an error status' => [static fn (): Throwable => new RequestException(answerToACall(503)), SteamApiException::class],
]);

test('numbers its attempts from one', function (): void {
    $reported = [];
    $call = callReporting($reported);

    expect([$call->nextAttempt(), $call->nextAttempt(), $call->nextAttempt()])->toBe([1, 2, 3]);
});

test('settles with what the call answered and reports nothing', function (): void {
    $reported = [];
    $response = answerToACall();

    expect(callReporting($reported)->settle(static fn (): Response => $response))->toBe($response)
        ->and($reported)->toBe([]);
});

test('maps what failed the call and reports it once', function (Closure $failure, string $expected): void {
    $reported = [];
    $call = callReporting($reported);

    $thrown = failureFrom(fn (): Response => $call->settle(static fn (): never => throw $failure()));

    expect($thrown::class)->toBe($expected)
        ->and($reported)->toBe([$thrown]);
})->with('failures a call maps');

test('leaves any other failure untouched and reports it once', function (): void {
    $reported = [];
    $call = callReporting($reported);
    $failure = new LogicException('The sender broke.');

    expect(failureFrom(fn (): Response => $call->settle(static fn (): never => throw $failure)))->toBe($failure)
        ->and($reported)->toBe([$failure]);
});

test('settles an async answer through the pipeline', function (): void {
    $reported = [];
    $piped = [];
    $response = answerToACall();

    expect(settledAsync(callReporting($reported), $response, $piped))->toBe($response)
        ->and($piped)->toBe([$response])
        ->and($reported)->toBe([]);
});

test('takes the response an async failure carries through the pipeline', function (Closure $answer, Closure $raise): void {
    $reported = [];
    $piped = [];
    $response = $answer();

    expect(settledAsync(callReporting($reported), $raise($response), $piped))->toBe($response)
        ->and($piped)->toBe([$response])
        ->and($reported)->toBe([]);
})->with([
    'an error status Saloon raised' => [static fn (): Response => answerToACall(503), static fn (Response $response): Throwable => new RequestException($response)],
    'an answer that raised its own exception' => [static fn (): Response => answerToACall(body: 'Not JSON'), static fn (Response $response): ?Throwable => $response->toException()],
]);

test('rejects an async call Steam never answered as SteamConnectionException, reported once', function (): void {
    $reported = [];
    $piped = [];
    $call = callReporting($reported);
    $failure = new FatalRequestException(connectionFailure(), answerToACall()->getPendingRequest());

    $thrown = failureFrom(static function () use ($call, $failure, &$piped): mixed {
        return settledAsync($call, $failure, $piped);
    });

    expect($thrown::class)->toBe(SteamConnectionException::class)
        ->and($reported)->toBe([$thrown])
        ->and($piped)->toBe([]);
});

test('rejects with an async failure no response raised, reported once', function (): void {
    $reported = [];
    $piped = [];
    $call = callReporting($reported);
    $failure = new LogicException('The sender broke.');

    expect(failureFrom(static function () use ($call, $failure, &$piped): mixed {
        return settledAsync($call, $failure, $piped);
    }))->toBe($failure)
        ->and($reported)->toBe([$failure])
        ->and($piped)->toBe([]);
});

test('rejects with a rejection that is not a Throwable, reported once', function (): void {
    $reported = [];
    $call = callReporting($reported);

    $thrown = failureFrom(fn (): mixed => settledAsync($call, 'The sender gave up.'));

    expect($thrown)->toBeInstanceOf(RejectionException::class)
        ->and($thrown->getReason())->toBe('The sender gave up.')
        ->and($reported)->toBe([$thrown]);
});

test('maps a failure the async pipeline raises and reports it once', function (): void {
    $reported = [];
    $call = callReporting($reported);

    $thrown = failureFrom(fn (): mixed => $call->settleAsync(
        static fn (): PromiseInterface => Create::promiseFor(answerToACall(503)),
        static fn (Response $response): never => throw new RequestException($response),
    )->wait());

    expect($thrown::class)->toBe(SteamApiException::class)
        ->and($thrown->getCode())->toBe(503)
        ->and($reported)->toBe([$thrown]);
});

test('refuses an answer cut off after a non-error status', function (): void {
    $cutOff = transferFailure(noFriends());

    $thrown = failureFrom(fn (): Response => Call::refuseFailedTransfer(answerToACall(200, $cutOff)));

    expect($thrown)->toBeInstanceOf(FatalRequestException::class)
        ->and($thrown->getPrevious())->toBe($cutOff);
});

test('lets through an answer that is whole or whose status speaks for itself', function (Closure $answer): void {
    $response = $answer();

    expect(Call::refuseFailedTransfer($response))->toBe($response);
})->with([
    'a 200 in full' => [static fn (): Response => answerToACall()],
    'a 404 cut off' => [static fn (): Response => answerToACall(404, transferFailure(new PsrResponse(404)))],
    'a 503 cut off' => [static fn (): Response => answerToACall(503, transferFailure(new PsrResponse(503)))],
    'a 200 with an exception from beyond Guzzle' => [static fn (): Response => answerToACall(200, new RuntimeException('Logged and ignored'))],
]);

test('hands a re-send the mock client of its call', function (): void {
    $reported = [];
    $mockClient = new MockClient([]);
    $response = answerToACall();
    $handed = null;

    expect(callReporting($reported, $mockClient)->resend(static function (?MockClient $given) use (&$handed, $response): Response {
        $handed = $given;

        return $response;
    }))->toBe($response)
        ->and($handed)->toBe($mockClient);
});

test('maps what failed a re-send and reports nothing', function (Closure $failure, string $expected): void {
    $reported = [];
    $call = callReporting($reported);

    $thrown = failureFrom(fn (): Response => $call->resend(static fn (): never => throw $failure()));

    expect($thrown::class)->toBe($expected)
        ->and($reported)->toBe([]);
})->with('failures a call maps');

test('leaves any other failure of a re-send untouched', function (): void {
    $reported = [];
    $call = callReporting($reported);
    $failure = new LogicException('The sender broke.');

    expect(failureFrom(fn (): Response => $call->resend(static fn (): never => throw $failure)))->toBe($failure)
        ->and($reported)->toBe([]);
});
