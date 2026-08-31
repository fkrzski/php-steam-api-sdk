<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Contracts\HasLanguage;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Resources\PlayersResource;
use Fkrzski\SteamApiSdk\Http\Resources\StatsResource;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Override;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Throwable;

class SteamConnector extends Connector
{
    use AlwaysThrowOnErrors;
    use HasRateLimits;

    public function __construct(
        public readonly SteamConfig $steamConfig,
    ) {}

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
     * Saloon merges the request query before booting, so the configured default only
     * fills the gap a request left open — an explicit language always wins.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        $request = $pendingRequest->getRequest();

        if (! $request instanceof HasLanguage || $request->language instanceof Language) {
            return;
        }

        if ($this->steamConfig->language instanceof Language) {
            $pendingRequest->query()->add('l', $this->steamConfig->language->value);
        }
    }

    /**
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
            default => new SteamApiException(
                sprintf('Steam API request failed with HTTP %d.', $status),
                $response,
            ),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'key' => $this->steamConfig->apiKey,
        ];
    }

    /**
     * @return array<Limit>
     */
    protected function resolveLimits(): array
    {
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
        return sprintf('SteamConnector:%s', hash('sha256', $this->steamConfig->apiKey));
    }

    protected function throwLimitException(Limit $limit): void
    {
        throw SteamRateLimitException::fromLimit($limit);
    }
}
