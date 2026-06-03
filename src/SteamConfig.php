<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

final readonly class SteamConfig
{
    public function __construct(
        public string $apiKey,
        public ?RateLimitStore $rateLimitStore = null,
    ) {}
}
