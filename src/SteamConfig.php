<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

final readonly class SteamConfig
{
    public function __construct(
        public ?string $apiKey = null,
        public ?RateLimitStore $rateLimitStore = null,
        public ?Language $language = null,
    ) {
        if ($apiKey !== null && trim($apiKey) === '') {
            throw ApiKeyNotConfiguredException::blank();
        }
    }
}
