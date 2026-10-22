<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Responses;

use Fkrzski\SteamApiSdk\Exceptions\UnreadableResponseException;
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

    /**
     * Requests decode the body in their own failure checks, so an unreadable one fails before they run.
     */
    #[Override]
    public function failed(): bool
    {
        return $this->unreadable() instanceof UnreadableResponseException || parent::failed();
    }

    #[Override]
    public function toException(): ?Throwable
    {
        return self::failedTransfer($this) ?? parent::toException();
    }

    #[Override]
    protected function createException(): Throwable
    {
        $exception = $this->unreadable() ?? parent::createException();

        self::$raisedBy ??= new WeakMap;
        self::$raisedBy[$exception] = $this;

        return $exception;
    }

    /**
     * An error status speaks for itself, and a request asking for another format reads its own body.
     */
    private function unreadable(): ?UnreadableResponseException
    {
        if ($this->clientError() || $this->serverError() || $this->getPendingRequest()->query()->get('format', 'json') !== 'json') {
            return null;
        }

        return $this->decodes() ? null : UnreadableResponseException::notJson($this);
    }

    /**
     * Decodes as json() does, into its cache: a stream that cannot rewind reads empty the second time.
     */
    private function decodes(): bool
    {
        if (isset($this->decodedJson)) {
            return true;
        }

        $decoded = json_decode($this->body() ?: '[]', true);

        if (! is_array($decoded)) {
            return false;
        }

        $this->decodedJson = $decoded;

        return true;
    }
}
