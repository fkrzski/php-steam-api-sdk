<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Exceptions\Request\FatalRequestException;

final class SteamConnectionException extends SteamApiException
{
    /**
     * Guzzle 7 quotes the full URI, key included, and error trackers walk getPrevious().
     */
    public static function fromFatalRequest(FatalRequestException $exception): self
    {
        $key = $exception->getPendingRequest()->query()->get('key');
        $reason = $exception->getMessage();

        return new self(sprintf(
            'Could not reach the Steam Web API: %s',
            is_string($key) ? str_replace($key, '***', $reason) : $reason,
        ));
    }
}
