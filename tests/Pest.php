<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Contracts\Sender;
use Saloon\Data\FactoryCollection;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Http\Senders\Factories\GuzzleMultipartBodyFactory;
use Saloon\Http\Senders\GuzzleSender;
use Saloon\MockConfig;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

MockConfig::throwOnMissingFixtures();

pest()->beforeEach(fn () => MemoryStore::clear());

/**
 * @return list<SteamId>
 */
function makeSteamIds(int $count): array
{
    $base = 76561198000000000;

    return array_map(
        static fn (int $offset): SteamId => SteamId::fromSteamId64((string) ($base + $offset)),
        range(0, $count - 1),
    );
}

/**
 * Saloon's MockResponse cannot raise a transport failure, so the queue is handed to
 * Guzzle's own handler underneath an injected sender instead.
 *
 * @param  list<PsrResponse|Throwable|Closure(RequestInterface): (PsrResponse|Throwable)>  $queue
 * @param  list<array<string, mixed>>|null  $history
 */
function connectorAnswering(array $queue, SteamConfig $config = new SteamConfig('any'), ?array &$history = null): SteamConnector
{
    $sender = new GuzzleSender;
    $sender->getHandlerStack()->setHandler(new MockHandler($queue));

    if ($history !== null) {
        $sender->getHandlerStack()->push(Middleware::history($history));
    }

    return new SteamConnector(new SteamConfig(...[...get_object_vars($config), 'sender' => $sender]));
}

/**
 * Unlike GuzzleSender, it resolves every status and hands a failure on exactly as queued.
 *
 * @param  list<PsrResponse|Throwable|array{PsrResponse, Throwable}|Closure(RequestInterface): (PsrResponse|Throwable)>  $queue
 */
function senderAnswering(array $queue): Sender
{
    return new class($queue) implements Sender
    {
        /**
         * @param  list<PsrResponse|Throwable|array{PsrResponse, Throwable}|Closure(RequestInterface): (PsrResponse|Throwable)>  $queue
         */
        public function __construct(private array $queue) {}

        public function getFactoryCollection(): FactoryCollection
        {
            $factory = new HttpFactory;

            return new FactoryCollection($factory, $factory, $factory, $factory, new GuzzleMultipartBodyFactory);
        }

        public function send(PendingRequest $pendingRequest): Response
        {
            $answer = $this->answer($pendingRequest);

            if ($answer instanceof Throwable) {
                throw $answer;
            }

            return $answer;
        }

        public function sendAsync(PendingRequest $pendingRequest): PromiseInterface
        {
            $answer = $this->answer($pendingRequest);

            return $answer instanceof Throwable ? Create::rejectionFor($answer) : Create::promiseFor($answer);
        }

        private function answer(PendingRequest $pendingRequest): Response|Throwable
        {
            $psrRequest = $pendingRequest->createPsrRequest();
            $answer = array_shift($this->queue) ?? throw new RuntimeException('The sender queue is empty.');
            $answer = $answer instanceof Closure ? $answer($psrRequest) : $answer;
            [$psrResponse, $senderException] = is_array($answer) ? $answer : [$answer, null];

            return $psrResponse instanceof Throwable
                ? $psrResponse
                : $pendingRequest->getResponseClass()::fromPsrResponse($psrResponse, $pendingRequest, $psrRequest, $senderException);
        }
    };
}

function connectionFailure(string $message = 'cURL error 28: Operation timed out after 5000 milliseconds'): ConnectException
{
    return new ConnectException($message, new PsrRequest('GET', 'https://api.steampowered.com'));
}

/**
 * Guzzle 7's cURL 18 or 56 once the headers are in; Guzzle 8's RequestException takes no response.
 */
function transferFailure(PsrResponse $response, string $message = 'cURL error 56: Recv failure: Connection reset by peer'): RequestException
{
    return new class($message, $response) extends RequestException
    {
        public function __construct(string $message, private readonly ResponseInterface $received)
        {
            parent::__construct($message, new PsrRequest('GET', 'https://api.steampowered.com'));
        }

        public function getResponse(): ResponseInterface
        {
            return $this->received;
        }
    };
}

function dailyHits(SteamConnector $connector): int
{
    return $connector->getLimits()[0]->update($connector->rateLimitStore())->getHits();
}

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
 * @return array<string, array{Closure(SteamConnector, ?Request=): mixed}>
 */
function everyWayToSend(): array
{
    return [
        'send' => [static fn (SteamConnector $connector, ?Request $request = null): mixed => $connector->send($request ?? friendListRequest())],
        'sendAsync' => [static fn (SteamConnector $connector, ?Request $request = null): mixed => $connector->sendAsync($request ?? friendListRequest())->wait()],
        'pool' => [static function (SteamConnector $connector, ?Request $request = null): never {
            $reasons = [];

            $connector->pool(
                [$request ?? friendListRequest()],
                exceptionHandler: static function (Throwable $reason) use (&$reasons): void {
                    $reasons[] = $reason;
                },
            )->send()->wait();

            throw $reasons[0] ?? new LogicException('Expected the pool to fail.');
        }],
    ];
}

function requestRaisingItsOwnException(): Request
{
    return new class extends Request
    {
        protected Method $method = Method::GET;

        public function resolveEndpoint(): string
        {
            return '/ISteamDirectory/GetCMList/v1/';
        }

        public function getRequestException(Response $response, ?Throwable $senderException): Throwable
        {
            return new RuntimeException('Not a list.');
        }
    };
}

function friendListRequest(): GetFriendListRequest
{
    return new GetFriendListRequest(SteamId::fromSteamId64('76561198148125221'));
}

/**
 * Stands in for Guzzle 8's NetworkTimeoutException, which Guzzle 7 does not ship.
 */
function networkFailure(string $message = 'cURL error 28: Operation timed out after 30000 milliseconds'): NetworkExceptionInterface&Throwable
{
    return new class($message) extends RuntimeException implements NetworkExceptionInterface
    {
        public function getRequest(): RequestInterface
        {
            return new PsrRequest('GET', 'https://api.steampowered.com');
        }
    };
}

function steamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function fakedConnector(MockClient $mockClient, SteamConfig $config = new SteamConfig('test-key')): SteamConnector
{
    return new SteamConnector($config)->withMockClient($mockClient);
}

/**
 * A feed of news items, newest first, with IDs counting up from `'1'` an hour apart.
 *
 * @return list<NewsItemFactory>
 */
function newsFeedItems(int $count): array
{
    return array_map(
        static fn (int $id): NewsItemFactory => NewsItemFactory::new()
            ->id((string) $id)
            ->publishedAt(new DateTimeImmutable('2026-10-01 12:00:00 UTC')->sub(new DateInterval(sprintf('PT%dH', $id)))),
        range(1, $count),
    );
}
