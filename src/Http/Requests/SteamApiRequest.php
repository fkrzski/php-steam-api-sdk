<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests;

use Fkrzski\SteamApiSdk\Contracts\HasRequiredPaths;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use LogicException;
use Override;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;
use WeakMap;

/**
 * Reads each answer once, inside the call, and is the one place Saloon's hooks ask. What a
 * request returns decides the retry: Saloon retries only its own RequestException, which a
 * request leaves to the connector by returning null, so a failure it returns is final at any status.
 *
 * @template TResult
 *
 * @internal
 */
abstract class SteamApiRequest extends Request implements HasRequiredPaths
{
    /**
     * @var WeakMap<Response, array{TResult|SteamApiException|null}>|null
     */
    private ?WeakMap $answers = null;

    /**
     * Never false: that would overrule Saloon's own 4xx and 5xx check.
     */
    #[Override]
    final public function hasRequestFailed(Response $response): ?bool
    {
        return $this->failure($response) instanceof SteamApiException ? true : null;
    }

    #[Override]
    final public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->failure($response);
    }

    /**
     * @return TResult
     */
    #[Override]
    final public function createDtoFromResponse(Response $response): mixed
    {
        $answer = $this->answer($response);

        if ($answer instanceof SteamApiException) {
            throw $answer;
        }

        return $answer ?? throw new LogicException('A failed answer builds no DTO.');
    }

    /**
     * A non-error answer, once its required paths are in place.
     *
     * @return TResult|SteamApiException
     */
    abstract protected function read(Response $response): mixed;

    /**
     * A 4xx or 5xx this method gives a meaning of its own; null leaves it to the connector.
     */
    protected function readError(Response $response): ?SteamApiException
    {
        return null;
    }

    private function failure(Response $response): ?SteamApiException
    {
        $answer = $this->answer($response);

        return $answer instanceof SteamApiException ? $answer : null;
    }

    /**
     * Boxed, because WeakMap reads a stored null as no entry at all.
     *
     * @return TResult|SteamApiException|null
     */
    private function answer(Response $response): mixed
    {
        $this->answers ??= new WeakMap;

        return ($this->answers[$response] ??= [
            $response->clientError() || $response->serverError() ? $this->readError($response) : $this->read($response),
        ])[0];
    }
}
