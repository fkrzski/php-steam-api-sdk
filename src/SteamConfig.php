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
    // LimitHelper::configureLimits() gives the plugin's own lock after a 429 this name.
    private const string TOO_MANY_ATTEMPTS_LIMIT = 'too_many_attempts_limit';

    public ?string $apiKey;

    /**
     * @param  array<Limit>|null  $rateLimits
     */
    public function __construct(
        #[SensitiveParameter]
        ?string $apiKey = null,
        public ?RateLimitStore $rateLimitStore = null,
        public ?Language $language = null,
        public ?float $connectTimeout = null,
        public ?float $requestTimeout = null,
        public int $tries = 1,
        public int $retryInterval = 0,
        public bool $exponentialBackoff = false,
        public ?array $rateLimits = null,
        public ?Sender $sender = null,
        public int $tooManyRequestsCooldown = 60,
    ) {
        $this->apiKey = $apiKey === null ? null : trim($apiKey);

        if ($this->apiKey === '') {
            throw ApiKeyNotConfiguredException::blank();
        }

        $this->assertTimeout('connectTimeout', $connectTimeout);
        $this->assertTimeout('requestTimeout', $requestTimeout);

        if ($tries < 1) {
            throw InvalidRetryException::tooFewTries($tries);
        }

        if ($retryInterval < 0) {
            throw InvalidRetryException::negativeInterval($retryInterval);
        }

        if ($rateLimits !== null) {
            $this->assertUniqueNames($rateLimits);
        }

        if ($tooManyRequestsCooldown < 1) {
            throw InvalidRateLimitException::tooShortCooldown($tooManyRequestsCooldown);
        }
    }

    private function assertTimeout(string $option, ?float $seconds): void
    {
        if ($seconds === null) {
            return;
        }

        if (! is_finite($seconds)) {
            throw InvalidTimeoutException::notFinite($option, $seconds);
        }

        if ($seconds < 0) {
            throw InvalidTimeoutException::negative($option, $seconds);
        }

        // Guzzle counts in whole milliseconds and refuses a positive value that rounds to none.
        if ($seconds > 0 && $seconds < 0.001) {
            throw InvalidTimeoutException::underOneMillisecond($option, $seconds);
        }

        // Guzzle casts the milliseconds to int, which PHP cannot do past PHP_INT_MAX.
        if ($seconds >= PHP_INT_MAX / 1000) {
            throw InvalidTimeoutException::tooLarge($option, $seconds);
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

            if ($name === self::TOO_MANY_ATTEMPTS_LIMIT) {
                throw InvalidRateLimitException::reservedName($key, $name);
            }

            if (isset($names[$name])) {
                throw InvalidRateLimitException::duplicateName($names[$name], $key, $name);
            }

            $names[$name] = $key;
        }
    }
}
