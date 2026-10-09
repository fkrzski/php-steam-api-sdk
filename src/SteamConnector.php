<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use DateTimeImmutable;
use DateTimeZone;
use Fkrzski\SteamApiSdk\Contracts\HasLanguage;
use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Contracts\SendsOptionalApiKey;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Hooks\RequestSending;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
use Fkrzski\SteamApiSdk\Http\Resources\AppsResource;
use Fkrzski\SteamApiSdk\Http\Resources\NewsResource;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\Http\Resources\WebApiResource;
use Fkrzski\SteamApiSdk\Http\Senders\SteamSender;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Override;
use Psr\Http\Message\RequestInterface;
use Saloon\Config;
use Saloon\Contracts\Sender;
use Saloon\Enums\PipeOrder;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Helpers\Debugger;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Http\Senders\GuzzleSender;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;
use Throwable;

class SteamConnector extends Connector
{
    use AlwaysThrowOnErrors;
    use HasRateLimits {
        bootHasRateLimits as private bootRateLimiter;
    }
    use HasTimeout;

    /**
     * @var list<callable(RequestSending): void>
     */
    private array $requestHooks = [];

    /**
     * @var list<callable(ResponseReceived): void>
     */
    private array $responseHooks = [];

    /**
     * @var list<callable(Throwable): void>
     */
    private array $failureHooks = [];

    /**
     * Saloon builds a new PendingRequest for every try of send() and numbers none of them.
     *
     * @var array<int, int>
     */
    private array $attempts = [];

    /**
     * Saloon's send() reads the retry settings straight off these properties, so the
     * config is copied rather than resolved.
     */
    public function __construct(
        public readonly SteamConfig $steamConfig,
    ) {
        $this->tries = $steamConfig->tries;
        $this->retryInterval = $steamConfig->retryInterval;
        $this->useExponentialBackoff = $steamConfig->exponentialBackoff;
    }

    public function resolveBaseUrl(): string
    {
        return 'https://api.steampowered.com';
    }

    public function players(): PlayersResource
    {
        return new PlayersResource($this);
    }

    public function users(): UsersResource
    {
        return new UsersResource($this);
    }

    public function stats(): StatsResource
    {
        return new StatsResource($this);
    }

    public function apps(): AppsResource
    {
        return new AppsResource($this);
    }

    public function news(): NewsResource
    {
        return new NewsResource($this);
    }

    public function webApi(): WebApiResource
    {
        return new WebApiResource($this);
    }

    /**
     * @param  callable(RequestSending): void  $hook
     */
    public function onRequest(callable $hook): static
    {
        $this->requestHooks[] = $hook;

        return $this;
    }

    /**
     * @param  callable(ResponseReceived): void  $hook
     */
    public function onResponse(callable $hook): static
    {
        $this->responseHooks[] = $hook;

        return $this;
    }

    /**
     * @param  callable(Throwable): void  $hook
     */
    public function onFailure(callable $hook): static
    {
        $this->failureHooks[] = $hook;

        return $this;
    }

    /**
     * Saloon merges the request query before booting, so both defaults act on a query
     * that is already complete: the key can be taken back out, and the configured
     * language only fills the gap a request left open — an explicit one always wins.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        $request = $pendingRequest->getRequest();

        if ($request instanceof SendsNoApiKey) {
            $pendingRequest->query()->remove('key');
        } elseif ($this->steamConfig->apiKey === null && ! $request instanceof SendsOptionalApiKey) {
            throw ApiKeyNotConfiguredException::forRequest($request);
        }

        if (! $request instanceof HasLanguage || $request->language instanceof Language) {
            return;
        }

        if ($this->steamConfig->language instanceof Language) {
            $pendingRequest->query()->add('l', $this->steamConfig->language->value);
        }
    }

    /**
     * The limiter turns a 429 into an exception in its first response pipe, so the hooks go in ahead of it.
     */
    public function bootHasRateLimits(PendingRequest $pendingRequest): void
    {
        $key = spl_object_id($pendingRequest->getRequest());
        $attempt = isset($this->attempts[$key]) ? ++$this->attempts[$key] : 1;
        $sentAt = null;

        $pendingRequest->middleware()
            ->onRequest(function (PendingRequest $pendingRequest) use ($attempt, &$sentAt): void {
                $sending = $this->sending($pendingRequest, $attempt);

                foreach ($this->requestHooks as $hook) {
                    $hook($sending);
                }

                $sentAt = microtime(true);
            }, order: PipeOrder::LAST)
            ->onResponse(function (Response $response) use ($attempt, &$sentAt): void {
                $duration = microtime(true) - $sentAt;
                $sending = $this->sending($response->getPendingRequest(), $attempt);
                $received = new ResponseReceived($sending->method, $sending->query, $attempt, $response->status(), $duration);

                foreach ($this->responseHooks as $hook) {
                    $hook($received);
                }
            }, order: PipeOrder::FIRST);

        $this->bootRateLimiter($pendingRequest);
    }

    /**
     * HasTimeout reads timeouts off connector properties; the readonly config would only be copied there.
     */
    public function getConnectTimeout(): float
    {
        return $this->steamConfig->connectTimeout ?? Config::$defaultConnectionTimeout;
    }

    public function getRequestTimeout(): float
    {
        return $this->steamConfig->requestTimeout ?? Config::$defaultRequestTimeout;
    }

    /**
     * Every attempt spends a request from the daily budget, so only what can change
     * by the next one is worth it: Steam unreachable, or a 5xx.
     */
    #[Override]
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        return $exception instanceof FatalRequestException || $exception->getResponse()->serverError();
    }

    /**
     * Not Saloon's fatal pipeline: that one runs before the retry decision.
     */
    #[Override]
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        $key = spl_object_id($request);
        $this->attempts[$key] = 0;

        try {
            return parent::send($request, $mockClient, $handleRetry);
        } catch (Throwable $throwable) {
            throw $this->failed($throwable);
        } finally {
            unset($this->attempts[$key]);
        }
    }

    /**
     * Covers pool(), which sends every request through here. Saloon's own version runs the
     * pipeline on a branch it drops, losing whatever a middleware throws on a 2xx.
     */
    #[Override]
    public function sendAsync(Request $request, ?MockClient $mockClient = null): PromiseInterface
    {
        return Utils::task(function () use ($request, $mockClient): PromiseInterface {
            $pendingRequest = $this->createPendingRequest($request, $mockClient)->setAsynchronous(true);

            $promise = $pendingRequest->hasFakeResponse()
                ? Create::promiseFor($this->createFakeResponse($pendingRequest))
                : $this->sender()->sendAsync($pendingRequest);

            return $promise->then($this->runResponsePipeline(...), $this->runResponsePipeline(...));
        })->otherwise(fn (Throwable $reason): never => throw $this->failed($reason));
    }

    /**
     * A pasted dump or a logging callback is where the key would leak, so the callable
     * gets a copy with it masked; the request Saloon sends is built separately.
     */
    #[Override]
    public function debugRequest(?callable $onRequest = null, bool $die = false): static
    {
        $onRequest ??= Debugger::symfonyRequestDebugger(...);

        return parent::debugRequest(
            static function (PendingRequest $pendingRequest, RequestInterface $psrRequest) use ($onRequest): void {
                $onRequest($pendingRequest, self::withMaskedKey($psrRequest));
            },
            $die,
        );
    }

    /**
     * A status with no meaning of its own returns null: Saloon then throws its
     * RequestException, the one type the retry loop catches, and send() maps it back.
     * 429 is absent on purpose: the rate limit plugin claims it in the response
     * pipeline, which sendAsync() runs on a failure too.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        $status = $response->status();
        $body = $response->body();

        // Key errors arrive as HTML, every real API error as JSON.
        if ($status === 400 && str_contains($body, "'key' is missing")) {
            return InvalidApiKeyException::missing($response);
        }

        // Which of the two a key error lands on is the endpoint's choice, and neither
        // separates an absent key from a rejected one.
        if (($status === 401 || $status === 403) && str_contains($body, 'key=')) {
            return InvalidApiKeyException::rejected($response);
        }

        return match ($status) {
            401, 403 => ProfileNotPublicException::fromResponse($response),
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        if ($this->steamConfig->apiKey === null) {
            return [];
        }

        return [
            'key' => $this->steamConfig->apiKey,
        ];
    }

    #[Override]
    protected function defaultSender(): Sender
    {
        return new SteamSender($this->steamConfig->sender ?? new GuzzleSender);
    }

    /**
     * Steam bills the daily budget to the API key, so a keyless connector meters
     * nothing locally and leans on the 429 limiter the plugin adds on its own.
     * The day is taken to turn at 00:00 UTC, which Valve does not document;
     * untilMidnightTonight() would follow date.timezone instead. Limits set on the
     * config replace all of this, with a key or without.
     *
     * @return array<Limit>
     */
    protected function resolveLimits(): array
    {
        if ($this->steamConfig->rateLimits !== null) {
            return $this->steamConfig->rateLimits;
        }

        if ($this->steamConfig->apiKey === null) {
            return [];
        }

        return [
            Limit::allow(100_000)
                ->everySeconds(86_400, 'utc_midnight')
                ->setExpiryTimestamp(new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp()),
        ];
    }

    /**
     * MemoryStore keeps its backing array static, so the default budget is shared
     * process-wide rather than per instance.
     */
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return $this->steamConfig->rateLimitStore ?? new MemoryStore;
    }

    /**
     * The quota belongs to the API key, not to the connector class. The key is hashed
     * so it never lands in a shared store.
     */
    protected function getLimiterPrefix(): ?string
    {
        if ($this->steamConfig->apiKey === null) {
            return 'SteamConnector:anonymous';
        }

        return sprintf('SteamConnector:%s', hash('sha256', $this->steamConfig->apiKey));
    }

    protected function throwLimitException(Limit $limit): void
    {
        throw SteamRateLimitException::fromLimit($limit);
    }

    /**
     * The plugin falls back to this interval only when a 429 carries no Retry-After.
     */
    protected function getTooManyAttemptsLimiter(): Limit
    {
        return Limit::custom($this->handleTooManyAttempts(...))->everySeconds($this->steamConfig->tooManyRequestsCooldown);
    }

    /**
     * Guzzle and the mock client reject a 4xx or 5xx before any pipeline runs, so a failure takes
     * the pipeline here as well and stays rejected, while a success resolves to what it returns.
     */
    private function runResponsePipeline(mixed $outcome): mixed
    {
        if ($outcome instanceof Response) {
            return $outcome->getPendingRequest()->executeResponsePipeline($outcome);
        }

        $response = match (true) {
            $outcome instanceof SteamApiException => $outcome->response,
            $outcome instanceof RequestException => $outcome->getResponse(),
            default => null,
        };

        if ($response instanceof Response) {
            $response->getPendingRequest()->executeResponsePipeline($response);
        }

        return Create::rejectionFor($outcome);
    }

    private function failed(Throwable $throwable): Throwable
    {
        $exception = match (true) {
            $throwable instanceof FatalRequestException => SteamConnectionException::fromFatalRequest($throwable),
            $throwable instanceof RequestException => SteamApiException::fromRequestException($throwable),
            default => $throwable,
        };

        foreach ($this->failureHooks as $hook) {
            try {
                $hook($exception);
            } catch (Throwable) {
                // A broken hook must not replace the exception the caller is owed.
            }
        }

        return $exception;
    }

    private function sending(PendingRequest $pendingRequest, int $attempt): RequestSending
    {
        $query = $pendingRequest->query()->all();

        unset($query['key']);

        return new RequestSending(trim($pendingRequest->getUri()->getPath(), '/'), $query, $attempt);
    }

    private static function withMaskedKey(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();

        return $request->withUri($uri->withQuery(implode('&', array_map(
            static fn (string $pair): string => str_starts_with($pair, 'key=') ? 'key=***' : $pair,
            explode('&', $uri->getQuery()),
        ))));
    }
}
