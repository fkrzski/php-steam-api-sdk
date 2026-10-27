<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Responses;

use Fkrzski\SteamApiSdk\Contracts\HasRequiredPaths;
use Fkrzski\SteamApiSdk\Exceptions\UnreadableResponseException;
use Override;
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
     * An exception a request builds itself may not carry the response it was raised for.
     */
    public static function raising(Throwable $exception): ?Response
    {
        return self::$raisedBy[$exception] ?? null;
    }

    /**
     * A request reads the answer in its failure check, so an unreadable one fails before it runs.
     */
    #[Override]
    public function failed(): bool
    {
        return $this->unreadable() instanceof UnreadableResponseException || parent::failed();
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

        if (! $this->decodes()) {
            return UnreadableResponseException::notJson($this);
        }

        $request = $this->getRequest();
        $missing = $request instanceof HasRequiredPaths
            ? array_find($request->requiredPaths($this), fn (string $path): bool => $this->json($path) === null)
            : null;

        return $missing === null ? null : UnreadableResponseException::missing($this, $missing);
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
