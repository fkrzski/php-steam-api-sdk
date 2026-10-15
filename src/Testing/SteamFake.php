<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing;

use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Fakes\AppsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\NewsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\PlayersFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\StatsFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\UsersFake;
use Fkrzski\SteamApiSdk\Testing\Fakes\WebApiFake;
use Saloon\Http\Faking\MockClient;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

/**
 * A Saloon mock for `withMockClient()`, faked through the same resource chain the
 * code under test calls.
 *
 * Each endpoint registers its {@see SteamResponse} builder under the request class
 * it sends and hands this mock back, so a chain always ends on something the
 * assertions can read.
 */
final class SteamFake extends MockClient
{
    public function __construct(array $mockData = [])
    {
        // The store is static, so a 429 an earlier test faked would still lock this one out.
        MemoryStore::clear();

        parent::__construct($mockData);
    }

    public function users(): UsersFake
    {
        return new UsersFake($this);
    }

    public function players(): PlayersFake
    {
        return new PlayersFake($this);
    }

    public function stats(): StatsFake
    {
        return new StatsFake($this);
    }

    public function apps(): AppsFake
    {
        return new AppsFake($this);
    }

    public function news(): NewsFake
    {
        return new NewsFake($this);
    }

    public function webApi(): WebApiFake
    {
        return new WebApiFake($this);
    }

    /**
     * Answers every request that has no response of its own with a rejected key.
     */
    public function invalidApiKey(): self
    {
        $this->addResponse(SteamResponse::invalidApiKey(), SteamConnector::class);

        return $this;
    }

    /**
     * Fails every request that has no response of its own before it reaches Steam.
     */
    public function connectionFailed(): self
    {
        $this->addResponse(SteamResponse::connectionFailed(), SteamConnector::class);

        return $this;
    }

    /**
     * Throttles every request that has no response of its own, as Steam does with a 429.
     */
    public function tooManyRequests(?int $retryAfter = null): self
    {
        $this->addResponse(SteamResponse::tooManyRequests($retryAfter), SteamConnector::class);

        return $this;
    }
}
