<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Closure;
use Fkrzski\SteamApiSdk\Contracts\HasLanguage;
use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Contracts\SendsOptionalApiKey;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Hooks\RequestSending;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
use Fkrzski\SteamApiSdk\Http\ApiKeyMask;
use Fkrzski\SteamApiSdk\Http\Call;
use Fkrzski\SteamApiSdk\Http\RateLimiter;
use Fkrzski\SteamApiSdk\Http\Resources\AppsResource;
use Fkrzski\SteamApiSdk\Http\Resources\NewsResource;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\Http\Resources\WebApiResource;
use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
use Fkrzski\SteamApiSdk\Http\Senders\SteamSender;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
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
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;
use Throwable;

final class SteamConnector extends Connector
{
    use AlwaysThrowOnErrors;
    use HasTimeout;

    #[Override]
    protected ?string $response = SteamApiResponse::class;

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
     * @var array<int, Call>
     */
    private array $calls = [];

    private bool $rateLimitingEnabled = true;

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

        // Ahead of every pipe a caller adds, debugResponse() included: a cut-off answer reaches none of them.
        $this->middleware()->onResponse(Call::refuseFailedTransfer(...), order: PipeOrder::FIRST);
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
     * Runs before every attempt goes out; an exception it throws fails the call and keeps the request unsent.
     *
     * @param  callable(RequestSending): void  $hook
     */
    public function onRequest(callable $hook): static
    {
        $this->requestHooks[] = $hook;

        return $this;
    }

    /**
     * Runs on every attempt's answer, a 429 included; an exception it throws fails the call.
     *
     * @param  callable(ResponseReceived): void  $hook
     */
    public function onResponse(callable $hook): static
    {
        $this->responseHooks[] = $hook;

        return $this;
    }

    /**
     * Runs once per failed call with the exception the caller gets; an exception it throws is dropped.
     *
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
        $key = $this->keyFor($request);

        if ($key === null) {
            $pendingRequest->query()->remove('key');
        }

        $limiter = $this->rateLimitingEnabled ? new RateLimiter($this->steamConfig, $key) : null;

        // The limiter turns a 429 into an exception in its first response pipe, so the hooks go in ahead of it.
        $this->bootHooks($pendingRequest, $limiter);

        $limiter?->boot($pendingRequest);

        if (! $request instanceof HasLanguage || $request->language instanceof Language) {
            return;
        }

        if ($this->steamConfig->language instanceof Language) {
            $pendingRequest->query()->add('l', $this->steamConfig->language->value);
        }
    }

    /**
     * The rate limit plugin's own checks read the configured key's budget, or the anonymous one without a key.
     *
     * @return array<Limit>
     */
    public function getLimits(): array
    {
        return $this->rateLimiter()->getLimits();
    }

    public function rateLimitStore(): RateLimitStore
    {
        return $this->rateLimiter()->rateLimitStore();
    }

    public function getExceededLimit(?float $threshold = null): ?Limit
    {
        return $this->rateLimiter()->getExceededLimit($threshold);
    }

    public function hasReachedRateLimit(?float $threshold = null): bool
    {
        return $this->rateLimiter()->hasReachedRateLimit($threshold);
    }

    public function useRateLimitPlugin(bool $enabled = true): static
    {
        $this->rateLimitingEnabled = $enabled;

        return $this;
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

        // The rate limit plugin re-sends what a sleep() limit held back from inside the first call's pipeline.
        if (isset($this->calls[$key])) {
            return $this->calls[$key]->resend(fn (?MockClient $mockClient): Response => parent::send($request, $mockClient, $handleRetry));
        }

        $call = new Call($mockClient, $this->reportFailure(...));

        return $this->during($key, $call, fn (): Response => $call->settle(fn (): Response => parent::send($request, $mockClient, $handleRetry)));
    }

    /**
     * Covers pool(), which sends every request through here. Saloon's own version runs the
     * pipeline on a branch it drops, losing whatever a middleware throws on a 2xx.
     */
    #[Override]
    public function sendAsync(Request $request, ?MockClient $mockClient = null): PromiseInterface
    {
        $key = spl_object_id($request);
        $call = new Call($mockClient, $this->reportFailure(...));

        return $call->settleAsync(
            function () use ($key, $call, $request, $mockClient): PromiseInterface {
                $pendingRequest = $this->during($key, $call, fn (): PendingRequest => $this->createPendingRequest($request, $mockClient));

                return $pendingRequest->hasFakeResponse()
                    ? Create::promiseFor($this->fakeResponse($pendingRequest))
                    : $this->sender()->sendAsync($pendingRequest->setAsynchronous(true));
            },
            fn (Response $response): Response => $this->during($key, $call, fn (): Response => $response->getPendingRequest()->executeResponsePipeline($response)),
        );
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
                $onRequest($pendingRequest, $psrRequest->withUri(ApiKeyMask::uri($psrRequest->getUri())));
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
     * Saloon records an async fake before rejecting with its exception; the sync path throws it unrecorded.
     */
    private function fakeResponse(PendingRequest $pendingRequest): mixed
    {
        $response = $this->createFakeResponse($pendingRequest);

        $pendingRequest->setAsynchronous(true);

        return $response;
    }

    /**
     * Only while the call runs synchronously: a promise nobody settles would otherwise hold it open for good.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $run
     * @return TResult
     */
    private function during(int $key, Call $call, Closure $run): mixed
    {
        $this->calls[$key] = $call;

        try {
            return $run();
        } finally {
            unset($this->calls[$key]);
        }
    }

    private function reportFailure(Throwable $exception): void
    {
        foreach ($this->failureHooks as $hook) {
            try {
                $hook($exception);
            } catch (Throwable) {
                // A broken hook must not replace the exception the caller is owed.
            }
        }
    }

    /**
     * The key a request goes out with, and so the budget Steam bills it to.
     */
    private function keyFor(Request $request): ?string
    {
        if ($request instanceof SendsNoApiKey) {
            return null;
        }

        if ($this->steamConfig->apiKey === null && ! $request instanceof SendsOptionalApiKey) {
            throw ApiKeyNotConfiguredException::forRequest($request);
        }

        return $this->steamConfig->apiKey;
    }

    private function bootHooks(PendingRequest $pendingRequest, ?RateLimiter $limiter): void
    {
        $key = spl_object_id($pendingRequest->getRequest());
        $attempt = isset($this->calls[$key]) ? $this->calls[$key]->nextAttempt() : 1;
        $sentAt = null;

        $pendingRequest->middleware()
            ->onRequest(function (PendingRequest $pendingRequest) use ($attempt, &$sentAt): void {
                $sending = $this->sending($pendingRequest, $attempt);

                foreach ($this->requestHooks as $hook) {
                    $hook($sending);
                }

                $sentAt = microtime(true);
            }, order: PipeOrder::LAST)
            ->onResponse(function (Response $response) use ($attempt, &$sentAt, $limiter): void {
                $duration = microtime(true) - $sentAt;
                $sending = $this->sending($response->getPendingRequest(), $attempt);
                $received = new ResponseReceived($sending->method, $sending->query, $attempt, $response->status(), $duration);

                try {
                    foreach ($this->responseHooks as $hook) {
                        $hook($received);
                    }
                } catch (Throwable $throwable) {
                    // Thrown here, the answer never reaches the limiter's pipe, which counts it and locks after a 429.
                    $limiter?->record($response);

                    throw $throwable;
                }
            }, order: PipeOrder::FIRST);
    }

    private function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->steamConfig, $this->steamConfig->apiKey);
    }

    private function sending(PendingRequest $pendingRequest, int $attempt): RequestSending
    {
        return new RequestSending(trim($pendingRequest->getUri()->getPath(), '/'), ApiKeyMask::query($pendingRequest->query()->all()), $attempt);
    }
}
