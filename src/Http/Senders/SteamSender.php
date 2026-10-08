<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Senders;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Client\NetworkExceptionInterface;
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
        } catch (NetworkExceptionInterface $networkException) {
            throw new FatalRequestException($networkException, $pendingRequest);
        }
    }

    public function sendAsync(PendingRequest $pendingRequest): PromiseInterface
    {
        return $this->sender->sendAsync($pendingRequest)->otherwise(
            static fn (mixed $reason): PromiseInterface => $reason instanceof NetworkExceptionInterface
                ? throw new FatalRequestException($reason, $pendingRequest)
                : Create::rejectionFor($reason),
        );
    }
}
