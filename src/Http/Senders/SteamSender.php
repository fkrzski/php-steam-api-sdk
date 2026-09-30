<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Senders;

use Override;
use Psr\Http\Client\NetworkExceptionInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\Http\Senders\GuzzleSender;

final class SteamSender extends GuzzleSender
{
    /**
     * Guzzle 8 raises a read timeout as a PSR-18 network exception the parent lets
     * through raw; wrapped, it takes the same path as a connection failure.
     */
    #[Override]
    public function send(PendingRequest $pendingRequest): Response
    {
        try {
            return parent::send($pendingRequest);
        } catch (NetworkExceptionInterface $networkException) {
            throw new FatalRequestException($networkException, $pendingRequest);
        }
    }
}
