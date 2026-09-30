<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Contracts\HasLanguage;
use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\Http\Senders\SteamSender;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Override;
use Saloon\Config;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
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
    use HasRateLimits;
    use HasTimeout;

    #[Override]
    protected string $defaultSender = SteamSender::class;

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
        } elseif ($this->steamConfig->apiKey === null) {
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
     * The trait reads timeouts off connector properties; the readonly config would only be copied there.
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
        try {
            return parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException $fatalRequestException) {
            throw SteamConnectionException::fromFatalRequest($fatalRequestException);
        } catch (RequestException $requestException) {
            throw SteamApiException::fromRequestException($requestException);
        }
    }

    /**
     * Covers pool(), which sends every request through here.
     */
    #[Override]
    public function sendAsync(Request $request, ?MockClient $mockClient = null): PromiseInterface
    {
        return parent::sendAsync($request, $mockClient)->otherwise(
            static fn (mixed $reason): PromiseInterface => match (true) {
                $reason instanceof FatalRequestException => throw SteamConnectionException::fromFatalRequest($reason),
                $reason instanceof RequestException => throw SteamApiException::fromRequestException($reason),
                default => Create::rejectionFor($reason),
            },
        );
    }

    /**
     * A status with no meaning of its own returns null: Saloon then throws its
     * RequestException, the one type the retry loop catches, and send() maps it back.
     * 429 is absent on purpose: the rate limit plugin runs as PipeOrder::FIRST
     * and throws before AlwaysThrowOnErrors (PipeOrder::LAST) reaches this.
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

    /**
     * Steam bills the daily budget to the API key, so a keyless connector meters
     * nothing locally and leans on the 429 limiter the plugin adds on its own.
     *
     * @return array<Limit>
     */
    protected function resolveLimits(): array
    {
        if ($this->steamConfig->apiKey === null) {
            return [];
        }

        return [
            Limit::allow(100_000)->everyDay(),
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
}
