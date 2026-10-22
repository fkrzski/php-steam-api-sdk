<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Fkrzski\SteamApiSdk\Http\ApiKeyMask;
use Saloon\Exceptions\Request\FatalRequestException;

final class SteamConnectionException extends SteamApiException
{
    /**
     * Guzzle 7 quotes the full URI, key included, and error trackers walk getPrevious().
     *
     * @internal
     */
    public static function fromFatalRequest(FatalRequestException $exception): self
    {
        return new self(self::naming($exception->getPendingRequest(), sprintf(
            'Could not reach the Steam Web API: %s',
            ApiKeyMask::message($exception->getMessage(), $exception->getPendingRequest()),
        )));
    }
}
