<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;
use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
use Fkrzski\SteamApiSdk\Http\Senders\SteamSender;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Contracts\Sender;
use Saloon\Data\FactoryCollection;
use Saloon\Enums\Method;
use Saloon\Enums\PipeOrder;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Http\Senders\GuzzleSender;

covers([SteamApiResponse::class, SteamConnectionException::class, SteamConnector::class, SteamSender::class]);

/**
 * @return array<string, array{Closure(PsrResponse): Throwable}>
 */
function cutOffAfterTheHeaders(): array
{
    return [
        'Guzzle 8 read timeout' => [static fn (PsrResponse $response): Throwable => class_exists(ResponseTimeoutException::class)
            ? new ResponseTimeoutException(
                'cURL error 28: Operation timed out after 30000 milliseconds with 18 out of 64 bytes received',
                new PsrRequest('GET', 'https://api.steampowered.com'),
                $response,
            )
            : test()->markTestSkipped('Guzzle 7 has no ResponseTimeoutException.')],
        'Guzzle 7 reset' => [static fn (PsrResponse $response): Throwable => transferFailure($response)],
    ];
}

test('a request that never reaches Steam raises SteamConnectionException', function (): void {
    $connector = connectorAnswering([connectionFailure()]);

    expect(fn (): mixed => $connector->send(friendListRequest()))
        ->toThrow(
            SteamConnectionException::class,
            'Could not reach the Steam Web API: cURL error 28: Operation timed out after 5000 milliseconds',
        );
});

test('a connection failure carries no response, no status and nothing chained', function (): void {
    $connector = connectorAnswering([connectionFailure()]);

    try {
        $connector->send(friendListRequest());
    } catch (SteamConnectionException $steamConnectionException) {
        expect($steamConnectionException)->toBeInstanceOf(SteamApiException::class)
            ->and($steamConnectionException->response)->toBeNull()
            ->and($steamConnectionException->getCode())->toBe(0)
            ->and($steamConnectionException->getPrevious())->toBeNull();

        return;
    }

    throw new RuntimeException('Expected the request to fail.');
});

test('a network failure Saloon lets through is wrapped like a connection failure', function (): void {
    $connector = connectorAnswering([networkFailure()]);

    try {
        $connector->send(friendListRequest());
    } catch (SteamConnectionException $steamConnectionException) {
        expect($steamConnectionException->getMessage())
            ->toBe('GetFriendList: Could not reach the Steam Web API: cURL error 28: Operation timed out after 30000 milliseconds')
            ->and($steamConnectionException->response)->toBeNull()
            ->and($steamConnectionException->getPrevious())->toBeNull();

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
            ->and($steamApiException->getMessage())->toBe('GetFriendList: Steam API request failed with HTTP 500.')
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
        ->and($thrown?->getMessage())->toBe('GetFriendList: Steam API request failed with HTTP 502.');
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

test('a transfer cut off after a 200 is retried, then raises SteamConnectionException', function (Closure $cutOff): void {
    $failure = $cutOff(noFriends());
    $history = [];
    $connector = connectorAnswering([$failure, $failure, $failure], new SteamConfig('any', tries: 3), $history);

    $thrown = failureFrom(fn (): mixed => $connector->send(friendListRequest()));

    expect($thrown::class)->toBe(SteamConnectionException::class)
        ->and($thrown->getMessage())->toBe('GetFriendList: Could not reach the Steam Web API: '.$failure->getMessage())
        ->and($thrown->getCode())->toBe(0)
        ->and($thrown->getPrevious())->toBeNull()
        ->and($history)->toHaveCount(3);
})->with(cutOffAfterTheHeaders());

test('a body cut off after a 200 raises SteamConnectionException however it was sent', function (Closure $send, Closure $cutOff, Closure $request, string $body): void {
    $connector = connectorAnswering([$cutOff(new PsrResponse(200, [], $body))]);

    expect(failureFrom(fn (): mixed => $send($connector, $request()))::class)->toBe(SteamConnectionException::class);
})->with(everyWayToSend())->with(cutOffAfterTheHeaders())->with([
    'GetFriendList, partial' => [friendListRequest(...), '{"friendslist":{"fri'],
    'GetOwnedGames, empty' => [static fn (): Request => new GetOwnedGamesRequest(steamId()), ''],
    'GetOwnedGames, partial' => [static fn (): Request => new GetOwnedGamesRequest(steamId()), '{"response":{"game'],
    'a request with its own response class, partial' => [static fn (): Request => new class extends Request
    {
        protected Method $method = Method::GET;

        protected ?string $response = Response::class;

        public function resolveEndpoint(): string
        {
            return '/ISteamDirectory/GetCMList/v1/';
        }
    }, '{"response":{"serverl'],
]);

test('a body cut off after a 200 reaches no response middleware however it was sent', function (Closure $send, Closure $cutOff): void {
    $reached = 0;
    $connector = connectorAnswering([$cutOff(noFriends())])
        ->debugResponse(static function () use (&$reached): void {
            $reached++;
        });
    $connector->middleware()->onResponse(static function (Response $response) use (&$reached): Response {
        $reached++;

        return $response;
    }, order: PipeOrder::FIRST);

    expect(failureFrom(fn (): mixed => $send($connector))::class)->toBe(SteamConnectionException::class)
        ->and($reached)->toBe(0);
})->with(everyWayToSend())->with(cutOffAfterTheHeaders());

test('a body cut off after an error status maps by that status', function (Closure $send, Closure $cutOff, int $status, string $expected): void {
    $connector = connectorAnswering([$cutOff(new PsrResponse($status, [], '{"error":"Not'))]);

    $thrown = failureFrom(fn (): mixed => $send($connector));

    expect($thrown::class)->toBe($expected)
        ->and($thrown->getCode())->toBe($status);
})->with(everyWayToSend())->with(cutOffAfterTheHeaders())->with([
    '404' => [404, SteamApiException::class],
    '503' => [503, SteamApiException::class],
]);

test('a 429 cut off mid-body still locks the key', function (Closure $cutOff): void {
    $history = [];
    $connector = connectorAnswering([$cutOff(new PsrResponse(429, [], '{"error":"Too'))], history: $history);

    $first = failureFrom(fn (): mixed => $connector->send(friendListRequest()));
    $second = failureFrom(fn (): mixed => $connector->send(friendListRequest()));

    expect($first::class)->toBe(SteamRateLimitException::class)
        ->and($second::class)->toBe(SteamRateLimitException::class)
        ->and($history)->toHaveCount(1);
})->with(cutOffAfterTheHeaders());

/**
 * What Saloon's GuzzleSender throws from 4.5.1 on for a body cut off after the headers, whatever the status.
 */
function senderFailingTheTransfer(Throwable $cause): Sender
{
    return new readonly class($cause) implements Sender
    {
        public function __construct(private Throwable $cause) {}

        public function getFactoryCollection(): FactoryCollection
        {
            return new GuzzleSender()->getFactoryCollection();
        }

        public function send(PendingRequest $pendingRequest): Response
        {
            throw $this->failure($pendingRequest);
        }

        public function sendAsync(PendingRequest $pendingRequest): PromiseInterface
        {
            return Create::rejectionFor($this->failure($pendingRequest));
        }

        private function failure(PendingRequest $pendingRequest): FatalRequestException
        {
            return new FatalRequestException($this->cause, $pendingRequest);
        }
    };
}

test('a transfer the sender fails after an error status maps by that status', function (Closure $send, int $status): void {
    $connector = new SteamConnector(new SteamConfig('any', sender: senderFailingTheTransfer(transferFailure(new PsrResponse($status, [], '{"error":"Not')))));

    $thrown = failureFrom(fn (): mixed => $send($connector));

    expect($thrown::class)->toBe(SteamApiException::class)
        ->and($thrown->getCode())->toBe($status);
})->with(everyWayToSend())->with([404, 503]);

test('a transfer the sender fails after a 429 still locks the key', function (): void {
    $connector = new SteamConnector(new SteamConfig('any', sender: senderFailingTheTransfer(transferFailure(new PsrResponse(429, [], '{"error":"Too')))));

    $first = failureFrom(fn (): mixed => $connector->send(friendListRequest()));
    $second = failureFrom(fn (): mixed => $connector->send(friendListRequest()));

    expect($first::class)->toBe(SteamRateLimitException::class)
        ->and($second::class)->toBe(SteamRateLimitException::class);
});

test('a transfer the sender fails after a 200 is still no answer', function (Closure $send): void {
    $connector = new SteamConnector(new SteamConfig('any', sender: senderFailingTheTransfer(transferFailure(new PsrResponse(200, [], '{"friendslist":{"fri')))));

    expect(failureFrom(fn (): mixed => $send($connector))::class)->toBe(SteamConnectionException::class);
})->with(everyWayToSend());

test('a failure the sender wraps without a Guzzle response is still no answer', function (Throwable $cause): void {
    $connector = new SteamConnector(new SteamConfig('any', sender: senderFailingTheTransfer($cause)));

    expect(failureFrom(fn (): mixed => $connector->send(friendListRequest()))::class)->toBe(SteamConnectionException::class);
})->with([
    'a Guzzle failure with no response' => [new RequestException('cURL error 52: Empty reply from server', new PsrRequest('GET', 'https://api.steampowered.com'))],
    "another client's failure carrying a response" => [new class extends RuntimeException
    {
        public function getResponse(): ResponseInterface
        {
            return new PsrResponse(200, [], '{"friendslist":{"fri');
        }
    }],
]);

test('the key stays masked when the cut-off transfer quotes the URI', function (): void {
    $connector = connectorAnswering(
        [static fn (RequestInterface $request): Throwable => transferFailure(noFriends(), sprintf('cURL error 56: Recv failure for %s', $request->getUri()))],
        new SteamConfig('secret-key'),
    );

    expect(failureFrom(fn (): mixed => $connector->send(friendListRequest()))->getMessage())->toBe(
        'GetFriendList: Could not reach the Steam Web API: cURL error 56: Recv failure for '
        .'https://api.steampowered.com/ISteamUser/GetFriendList/v1/?key=***&steamid=76561198148125221',
    );
});

test('a transfer cut off after the headers reaches onFailure and never onResponse', function (Closure $send): void {
    $failures = [];
    $responses = 0;
    $connector = connectorAnswering([transferFailure(noFriends())])
        ->onResponse(static function () use (&$responses): void {
            $responses++;
        })
        ->onFailure(static function (Throwable $throwable) use (&$failures): void {
            $failures[] = $throwable::class;
        });

    failureFrom(fn (): mixed => $send($connector));

    expect($failures)->toBe([SteamConnectionException::class])
        ->and($responses)->toBe(0);
})->with(everyWayToSend());

test('a sender beyond Guzzle that resolves a cut-off transfer raises SteamConnectionException', function (Closure $send): void {
    $connector = sendingThrough(senderAnswering([[noFriends(), transferFailure(noFriends())]]));

    expect(failureFrom(fn (): mixed => $send($connector))::class)->toBe(SteamConnectionException::class);
})->with(everyWayToSend());

test('a sender exception from beyond Guzzle leaves the status to speak', function (Closure $send): void {
    $connector = sendingThrough(senderAnswering([[new PsrResponse(404), new RuntimeException('Not Found')]]));

    $thrown = failureFrom(fn (): mixed => $send($connector));

    expect($thrown::class)->toBe(SteamApiException::class)
        ->and($thrown->getMessage())->toBe('GetFriendList: Steam API request failed with HTTP 404.')
        ->and($thrown->getCode())->toBe(404);
})->with(everyWayToSend());

test('a 200 a sender beyond Guzzle resolves with its own exception is still an answer', function (): void {
    $connector = sendingThrough(senderAnswering([[noFriends(), new RuntimeException('Logged and ignored')]]));

    expect($connector->send(friendListRequest())->status())->toBe(200);
});
