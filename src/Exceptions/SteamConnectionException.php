<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Throwable;

final class SteamConnectionException extends SteamApiException
{
    public static function fromFatalRequest(FatalRequestException $exception): self
    {
        return new self(
            sprintf('Could not reach the Steam Web API: %s', $exception->getMessage()),
            null,
            $exception,
        );
    }

    public static function fromNetworkFailure(NetworkExceptionInterface&Throwable $exception): self
    {
        return new self(
            sprintf('Could not reach the Steam Web API: %s', $exception->getMessage()),
            null,
            $exception,
        );
    }
}
