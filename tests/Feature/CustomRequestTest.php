<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Enums\Method;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([SteamConnector::class]);

beforeEach(function (): void {
    MemoryStore::clear();
});

/**
 * The hasRequestFailed() example from docs/custom-requests.mdx, as written there.
 */
function cmListRequest(): Request
{
    return new class extends Request
    {
        protected Method $method = Method::GET;

        public function resolveEndpoint(): string
        {
            return '/ISteamDirectory/GetCMList/v1/';
        }

        public function hasRequestFailed(Response $response): ?bool
        {
            if ($response->status() !== 200) {
                return null;
            }

            return $response->json('response.result') !== 1;
        }
    };
}

function cmListConnector(MockClient $mockClient, int $tries = 1): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig('any', tries: $tries));
    $connector->withMockClient($mockClient);

    return $connector;
}

function cmListFailure(MockResponse|Fixture $answer): SteamApiException
{
    try {
        cmListConnector(new MockClient([$answer]))->send(cmListRequest());
    } catch (SteamApiException $steamApiException) {
        return $steamApiException;
    }

    throw new RuntimeException('Expected the request to fail.');
}

test('a key error still raises InvalidApiKeyException', function (): void {
    expect(cmListFailure(MockResponse::fixture('Errors/invalid-key')))->toBeInstanceOf(InvalidApiKeyException::class);
});

test('an HTML 5xx is still retried', function (): void {
    $mockClient = new MockClient([
        MockResponse::make('<html>Server Error</html>', 503),
        MockResponse::make(['response' => ['result' => 1]]),
    ]);

    $response = cmListConnector($mockClient, tries: 3)->send(cmListRequest());

    expect($response->status())->toBe(200);
    $mockClient->assertSentCount(2);
});

test('a 500 fails even when its payload reads as a success', function (): void {
    $exception = cmListFailure(MockResponse::make(['response' => ['result' => 1]], 500));

    expect($exception::class)->toBe(SteamApiException::class)
        ->and($exception->getCode())->toBe(500);
});

test('a failure in a 200 payload fails the call', function (): void {
    $exception = cmListFailure(MockResponse::make(['response' => ['result' => 2]]));

    expect($exception::class)->toBe(SteamApiException::class)
        ->and($exception->getCode())->toBe(200);
});
