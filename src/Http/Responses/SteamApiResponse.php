<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Responses;

use GuzzleHttp\Exception\RequestException;
use Override;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Response;
use Throwable;

/**
 * GuzzleSender's async path asks the response for its exception, before any SDK code sees it.
 *
 * @internal
 */
final class SteamApiResponse extends Response
{
    /**
     * A body cut off after a non-error status is no answer; an error status still speaks for itself.
     */
    public static function failedTransfer(Response $response): ?FatalRequestException
    {
        $senderException = $response->getSenderException();

        if (! $senderException instanceof RequestException || $response->clientError() || $response->serverError()) {
            return null;
        }

        return new FatalRequestException($senderException, $response->getPendingRequest());
    }

    #[Override]
    public function toException(): ?Throwable
    {
        return self::failedTransfer($this) ?? parent::toException();
    }
}
