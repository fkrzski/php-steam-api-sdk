<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

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
