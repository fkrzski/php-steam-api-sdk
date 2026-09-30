<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use Saloon\Http\Response;

final class InvalidServerAddressException extends SteamApiException
{
    public static function forAddress(string $address, Response $response): self
    {
        return new self(
            sprintf('Steam rejected "%s" as a server address: it takes an IPv4 address, optionally with a query port.', $address),
            $response,
        );
    }
}
