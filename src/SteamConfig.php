<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidTimeoutException;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;

final readonly class SteamConfig
{
    public function __construct(
        public ?string $apiKey = null,
        public ?RateLimitStore $rateLimitStore = null,
        public ?Language $language = null,
        public ?float $connectTimeout = null,
        public ?float $requestTimeout = null,
    ) {
        if ($apiKey !== null && trim($apiKey) === '') {
            throw ApiKeyNotConfiguredException::blank();
        }

        if ($connectTimeout !== null && $connectTimeout < 0) {
            throw InvalidTimeoutException::negative('connectTimeout', $connectTimeout);
        }

        if ($requestTimeout !== null && $requestTimeout < 0) {
            throw InvalidTimeoutException::negative('requestTimeout', $requestTimeout);
        }
    }
}
