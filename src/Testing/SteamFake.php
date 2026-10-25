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
use GuzzleHttp\Exception\ConnectException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

/**
 * A Saloon mock for `withMockClient()`, faked through the same resource chain the
 * code under test calls.
 *
 * Each endpoint registers the response Steam sends it under the request class it
 * sends and hands this mock back, so a chain always ends on something the
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
     * Answers every request that has no response of its own with a rejected key, which Steam sends only to requests carrying one.
     * The connector matches on `key=` in the body, so the echoed query string is what makes this an `InvalidApiKeyException`.
     */
    public function invalidApiKey(): self
    {
        $this->addResponse(MockResponse::make(
            <<<'HTML'
                <html>
                    <head>
                        <title>Forbidden</title>
                    </head>
                    <body>
                        <h1>Forbidden</h1>
                        Access is denied. Retrying will not help. Please verify your <pre>key=</pre>
                        parameter.
                    </body>
                </html>
                HTML,
            403,
        ), SteamConnector::class);

        return $this;
    }

    /**
     * Fails every request that has no response of its own before it reaches Steam, retried like a real outage.
     * Saloon throws it before recording a response, so `getRecordedResponses()` and the `assertSent` family never see the attempt.
     */
    public function connectionFailed(): self
    {
        $this->addResponse(MockResponse::make()->throw(
            static fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(
                new ConnectException(
                    'cURL error 28: Operation timed out after 10000 milliseconds',
                    $pendingRequest->createPsrRequest(),
                ),
                $pendingRequest,
            ),
        ), SteamConnector::class);

        return $this;
    }

    /**
     * Throttles every request that has no response of its own, locking out the later ones sent the same way, with the key or without.
     * The lock lasts `Retry-After` seconds, or `tooManyRequestsCooldown` without one.
     */
    public function tooManyRequests(?int $retryAfter = null): self
    {
        $this->addResponse(
            MockResponse::make([], 429, $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter]),
            SteamConnector::class,
        );

        return $this;
    }
}
