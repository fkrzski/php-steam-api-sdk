<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk;

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRateLimitException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRetryException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidTimeoutException;
use Fkrzski\SteamApiSdk\Http\RateLimiter;
use Saloon\Contracts\Sender;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use SensitiveParameter;

final readonly class SteamConfig
{
    public ?string $apiKey;

    /**
     * @param  string|null  $apiKey  Trimmed; a blank one throws, so pass null to send none.
     * @param  RateLimitStore|null  $rateLimitStore  MemoryStore by default, which no other process sees.
     * @param  int  $tries  Every attempt counts against the rate limits.
     * @param  int  $retryInterval  Milliseconds, where the timeouts and the cool-down take seconds.
     * @param  array<Limit>|null  $rateLimits  Null keeps 100 000 a day with a key, [] meters nothing locally.
     * @param  Sender|null  $sender  Its middleware sees the request as Steam does, key included.
     * @param  int  $tooManyRequestsCooldown  Seconds a 429 without Retry-After refuses requests locally.
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
     * The limiter replaces every prefix with its own, so names are compared without one.
     *
     * @param  array<Limit>  $rateLimits
     */
    private function assertUniqueNames(array $rateLimits): void
    {
        $names = [];

        foreach ($rateLimits as $key => $limit) {
            $name = substr((clone $limit)->setPrefix('')->getName(), 1);

            if ($name === RateLimiter::TOO_MANY_ATTEMPTS_LIMIT) {
                throw InvalidRateLimitException::reservedName($key, $name);
            }

            if (isset($names[$name])) {
                throw InvalidRateLimitException::duplicateName($names[$name], $key, $name);
            }

            $names[$name] = $key;
        }
    }
}
