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

    public static function fromRequestException(RequestException $exception): self
    {
        return new self(
            sprintf('Steam API request failed with HTTP %d.', $exception->getStatus()),
            $exception->getResponse(),
            $exception,
        );
    }
}
