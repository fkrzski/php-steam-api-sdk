<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use RuntimeException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Throwable;

class SteamApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $response instanceof Response ? self::naming($response->getPendingRequest(), $message) : $message,
            $response?->status() ?? 0,
            $previous,
        );
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

    /**
     * Steam paths read /Interface/Method/vN/; only the path is read, so the key stays out.
     */
    protected static function naming(PendingRequest $pendingRequest, string $message): string
    {
        $method = explode('/', trim($pendingRequest->getUri()->getPath(), '/'))[1] ?? null;

        return $method === null ? $message : sprintf('%s: %s', $method, $message);
    }
}
