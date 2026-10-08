<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRateLimitException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRetryException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidTimeoutException;
use Saloon\Contracts\Sender;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use SensitiveParameter;

final readonly class SteamConfig
{
    /**
     * @param  array<Limit>|null  $rateLimits
     */
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
        public ?array $rateLimits = null,
        public ?Sender $sender = null,
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

        if ($rateLimits !== null) {
            $this->assertUniqueNames($rateLimits);
        }
    }

    /**
     * The connector replaces every prefix with its own, so names are compared without one.
     *
     * @param  array<Limit>  $rateLimits
     */
    private function assertUniqueNames(array $rateLimits): void
    {
        $names = [];

        foreach ($rateLimits as $key => $limit) {
            $name = substr((clone $limit)->setPrefix('')->getName(), 1);

            if (isset($names[$name])) {
                throw InvalidRateLimitException::duplicateName($names[$name], $key, $name);
            }

            $names[$name] = $key;
        }
    }
}
