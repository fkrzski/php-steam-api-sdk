<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Responses;

use GuzzleHttp\Exception\RequestException;
use Override;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Response;
use Throwable;
use WeakMap;

/**
 * GuzzleSender's async path asks the response for its exception, before any SDK code sees it.
 *
 * @internal
 */
final class SteamApiResponse extends Response
{
    /**
     * @var WeakMap<Throwable, Response>|null
     */
    private static ?WeakMap $raisedBy = null;

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

    /**
     * An exception a request builds itself may not carry the response it was raised for.
     */
    public static function raising(Throwable $exception): ?Response
    {
        return self::$raisedBy[$exception] ?? null;
    }

    #[Override]
    public function toException(): ?Throwable
    {
        return self::failedTransfer($this) ?? parent::toException();
    }

    #[Override]
    protected function createException(): Throwable
    {
        $exception = parent::createException();

        self::$raisedBy ??= new WeakMap;
        self::$raisedBy[$exception] = $this;

        return $exception;
    }
}
