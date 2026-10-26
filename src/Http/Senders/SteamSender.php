<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Senders;

use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
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
            $response = $this->sender->send($pendingRequest);
        } catch (NetworkExceptionInterface $networkException) {
            throw new FatalRequestException($networkException, $pendingRequest);
        } catch (FatalRequestException $fatalRequestException) {
            $response = self::cutOffAnswer($fatalRequestException) ?? throw $fatalRequestException;
        }

        $failedTransfer = SteamApiResponse::failedTransfer($response);

        return $failedTransfer instanceof FatalRequestException ? throw $failedTransfer : $response;
    }

    public function sendAsync(PendingRequest $pendingRequest): PromiseInterface
    {
        try {
            $promise = $this->sender->sendAsync($pendingRequest);
        } catch (NetworkExceptionInterface $networkException) {
            $promise = Create::rejectionFor($networkException);
        }

        return $promise->otherwise(
            static fn (mixed $reason): PromiseInterface|Response => match (true) {
                $reason instanceof NetworkExceptionInterface => throw new FatalRequestException($reason, $pendingRequest),
                $reason instanceof FatalRequestException => self::cutOffAnswer($reason) ?? Create::rejectionFor($reason),
                default => Create::rejectionFor($reason),
            },
        );
    }

    /**
     * Saloon 4.5.1 fails a body cut off after the headers at any status; failedTransfer() decides by it again.
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
