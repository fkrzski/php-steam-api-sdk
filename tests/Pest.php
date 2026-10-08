<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Saloon\Contracts\Sender;
use Saloon\Data\FactoryCollection;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\Http\Senders\Factories\GuzzleMultipartBodyFactory;
use Saloon\Http\Senders\GuzzleSender;
use Saloon\MockConfig;

MockConfig::throwOnMissingFixtures();

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
 * @param  list<PsrResponse|Throwable|Closure(RequestInterface): (PsrResponse|Throwable)>  $queue
 */
function senderAnswering(array $queue): Sender
{
    return new class($queue) implements Sender
    {
        /**
         * @param  list<PsrResponse|Throwable|Closure(RequestInterface): (PsrResponse|Throwable)>  $queue
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

            return $answer instanceof Throwable
                ? $answer
                : $pendingRequest->getResponseClass()::fromPsrResponse($answer, $pendingRequest, $psrRequest);
        }
    };
}

function connectionFailure(string $message = 'cURL error 28: Operation timed out after 5000 milliseconds'): ConnectException
{
    return new ConnectException($message, new PsrRequest('GET', 'https://api.steampowered.com'));
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
