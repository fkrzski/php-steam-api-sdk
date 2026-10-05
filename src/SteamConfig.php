<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRetryException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidTimeoutException;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use SensitiveParameter;

final readonly class SteamConfig
{
    public function __construct(
        #[SensitiveParameter]
        public ?string $apiKey = null,
        public ?RateLimitStore $rateLimitStore = null,
        public ?Language $language = null,
        public ?float $connectTimeout = null,
        public ?float $requestTimeout = null,
        public int $tries = 1,
        public int $retryInterval = 0,
        public bool $exponentialBackoff = false,
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

        if ($tries < 1) {
            throw InvalidRetryException::tooFewTries($tries);
        }

        if ($retryInterval < 0) {
            throw InvalidRetryException::negativeInterval($retryInterval);
        }
    }
}
