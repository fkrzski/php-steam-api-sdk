<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Senders;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Contracts\Sender;
use Saloon\Data\FactoryCollection;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

/**
 * PSR-18 reports a request that got no answer as a network exception, Guzzle 8's read timeout
 * among them; Saloon's retry loop only sees one once it is a FatalRequestException.
 */
final readonly class SteamSender implements Sender
{
    /** @internal */
    public function __construct(
        private Sender $sender,
    ) {}

    public function getFactoryCollection(): FactoryCollection
    {
        return $this->sender->getFactoryCollection();
    }

    public function send(PendingRequest $pendingRequest): Response
    {
        try {
            return $this->sender->send($pendingRequest);
        } catch (NetworkExceptionInterface|FatalRequestException $failure) {
            return self::recovered($failure, $pendingRequest);
        }
    }

    public function sendAsync(PendingRequest $pendingRequest): PromiseInterface
    {
        try {
            $promise = $this->sender->sendAsync($pendingRequest);
        } catch (NetworkExceptionInterface $networkException) {
            $promise = Create::rejectionFor($networkException);
        }

        return $promise->otherwise(
            static fn (mixed $reason): PromiseInterface|Response => $reason instanceof NetworkExceptionInterface || $reason instanceof FatalRequestException
                ? self::recovered($reason, $pendingRequest)
                : Create::rejectionFor($reason),
        );
    }

    /**
     * Throws a failure that left no answer behind.
     */
    private static function recovered(NetworkExceptionInterface|FatalRequestException $failure, PendingRequest $pendingRequest): Response
    {
        if ($failure instanceof NetworkExceptionInterface) {
            throw new FatalRequestException($failure, $pendingRequest);
        }

        return self::cutOffAnswer($failure) ?? throw $failure;
    }

    /**
     * Saloon 4.5.1 fails a body cut off after the headers at any status; Call::refuseFailedTransfer() decides by it again.
     */
    private static function cutOffAnswer(FatalRequestException $exception): ?Response
    {
        $cutOff = $exception->getPrevious();
        $psrResponse = $cutOff instanceof RequestException && method_exists($cutOff, 'getResponse') ? $cutOff->getResponse() : null;

        if (! $psrResponse instanceof ResponseInterface) {
            return null;
        }

        $pendingRequest = $exception->getPendingRequest();

        return $pendingRequest->getResponseClass()::fromPsrResponse($psrResponse, $pendingRequest, $cutOff->getRequest(), $cutOff);
    }
}
