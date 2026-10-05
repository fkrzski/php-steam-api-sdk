<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use RuntimeException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;
use Throwable;

class SteamApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    /**
     * Not chained: Saloon carries Guzzle's exception, which in 7.x quotes the URI, key included.
     */
    public static function fromRequestException(RequestException $exception): self
    {
        return new self(
            sprintf('Steam API request failed with HTTP %d.', $exception->getStatus()),
            $exception->getResponse(),
        );
    }

    public static function fromUnsuccessfulResponse(Response $response, string $reason): self
    {
        return new self(
            sprintf('Steam API request failed: %s', $reason),
            $response,
        );
    }
}
