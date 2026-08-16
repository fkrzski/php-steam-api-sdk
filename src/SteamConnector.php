<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Override;
use Saloon\Http\Connector;
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

        if ($status === 403 && str_contains($body, 'key=')) {
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

    protected function resolveRateLimitStore(): RateLimitStore
    {
        return $this->steamConfig->rateLimitStore ?? new MemoryStore;
    }

    protected function throwLimitException(Limit $limit): void
    {
        throw SteamRateLimitException::fromLimit($limit);
    }
}
