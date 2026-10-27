<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http;

use Closure;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;
use Throwable;

/**
 * One call however many times it goes out: Saloon numbers none of its tries, and the rate limit
 * plugin's re-send drops its mock client.
 *
 * @internal
 */
final class Call
{
    private int $attempts = 0;

    public function __construct(
        public readonly ?MockClient $mockClient,
        private readonly Closure $reportFailure,
    ) {}

    /**
     * A body cut off after a non-error status is no answer; an error status still speaks for itself.
     */
    public static function refuseFailedTransfer(Response $response): Response
    {
        $senderException = $response->getSenderException();

        if (! $senderException instanceof GuzzleRequestException || $response->clientError() || $response->serverError()) {
            return $response;
        }

        throw new FatalRequestException($senderException, $response->getPendingRequest());
    }

    public function nextAttempt(): int
    {
        return ++$this->attempts;
    }

    /**
     * @param  Closure(): Response  $send
     */
    public function settle(Closure $send): Response
    {
        try {
            return $send();
        } catch (Throwable $throwable) {
            throw $this->failed($throwable);
        }
    }

    /**
     * Guzzle rejects a 4xx or 5xx before any pipeline runs, so a failure takes the pipeline as well
     * and settles with what it returns, as send() would.
     *
     * @param  Closure(): PromiseInterface  $send
     */
    public function settleAsync(Closure $send, Closure $pipeline): PromiseInterface
    {
        return Utils::task(function () use ($send, $pipeline): PromiseInterface {
            $answered = fn (mixed $outcome): PromiseInterface|Response => $this->answered($outcome, $pipeline);

            return $send()->then($answered, $answered);
        })->otherwise(fn (mixed $reason): never => throw $this->failed(Create::exceptionFor($reason)));
    }

    /**
     * The first send reports the call's failure, so a re-send only maps its own.
     *
     * @param  Closure(?MockClient): Response  $send
     */
    public function resend(Closure $send): Response
    {
        try {
            return $send($this->mockClient);
        } catch (Throwable $throwable) {
            throw $this->mapped($throwable);
        }
    }

    /**
     * @param  Closure(Response): Response  $pipeline
     */
    private function answered(mixed $outcome, Closure $pipeline): PromiseInterface|Response
    {
        $response = match (true) {
            $outcome instanceof Response => $outcome,
            $outcome instanceof RequestException => $outcome->getResponse(),
            $outcome instanceof Throwable => SteamApiResponse::raising($outcome),
            default => null,
        };

        return $response instanceof Response ? $pipeline($response) : Create::rejectionFor($outcome);
    }

    private function failed(Throwable $throwable): Throwable
    {
        $exception = $this->mapped($throwable);

        ($this->reportFailure)($exception);

        return $exception;
    }

    private function mapped(Throwable $throwable): Throwable
    {
        return match (true) {
            $throwable instanceof FatalRequestException => SteamConnectionException::fromFatalRequest($throwable),
            $throwable instanceof RequestException => SteamApiException::fromRequestException($throwable),
            default => $throwable,
        };
    }
}
