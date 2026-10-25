<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use GuzzleHttp\Exception\ConnectException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * Wraps the factory payloads in the envelope each Steam endpoint answers with.
 *
 * Every endpoint nests its payload its own way and refuses in its own shape, so both
 * live here rather than in every test that fakes a call.
 */
final class SteamResponse
{
    /**
     * `servertimestring` is the same moment in US Pacific time without an offset,
     * which nothing reads; it is here because Steam sends it.
     */
    public static function serverInfo(DateTimeInterface $time): MockResponse
    {
        $pacific = DateTimeImmutable::createFromInterface($time)->setTimezone(new DateTimeZone('America/Los_Angeles'));

        return MockResponse::make([
            'servertime' => $time->getTimestamp(),
            'servertimestring' => sprintf('%s %2d %s', $pacific->format('D M'), $pacific->format('j'), $pacific->format('H:i:s Y')),
        ]);
    }

    public static function supportedApiList(WebApiInterfaceFactory ...$interfaces): MockResponse
    {
        return MockResponse::make([
            'apilist' => [
                'interfaces' => array_map(
                    static fn (WebApiInterfaceFactory $interface): array => $interface->toArray(),
                    $interfaces,
                ),
            ],
        ]);
    }

    /**
     * A key `GetSupportedAPIList` rejects, answered with a 403 and an empty list rather
     * than the `key=` page {@see self::invalidApiKey()} returns. The request raises
     * `InvalidApiKeyException` itself before the connector can read it as a hidden profile.
     */
    public static function supportedApiListInvalidKey(): MockResponse
    {
        return MockResponse::make(['apilist' => ['interfaces' => []]], 403);
    }

    /**
     * A key Steam rejects. The connector matches on `key=` in the body, so the
     * echoed query string is what makes this an `InvalidApiKeyException`.
     */
    public static function invalidApiKey(): MockResponse
    {
        return MockResponse::make(
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
        );
    }

    /**
     * The same rejected key on 401 instead of 403. Which status a key error
     * lands on is the endpoint's choice — `GetCommunityBadgeProgress` picks
     * this one, and without the `key=` marker it would read as a hidden profile.
     */
    public static function apiKeyUnauthorized(): MockResponse
    {
        return MockResponse::make(
            <<<'HTML'
                <html>
                    <head>
                        <title>Unauthorized</title>
                    </head>
                    <body>
                        <h1>Unauthorized</h1>
                        Access is denied. Retrying will not help. Please verify your <pre>key=</pre>
                        parameter.
                    </body>
                </html>
                HTML,
            401,
        );
    }

    /**
     * A request that reached Steam without a key at all. Steam answers in HTML
     * rather than JSON, which is how the connector tells it apart from a real
     * API error on the same status.
     */
    public static function apiKeyMissing(): MockResponse
    {
        return MockResponse::make(
            <<<'HTML'
                <html>
                    <head>
                        <title>Bad Request</title>
                    </head>
                    <body>
                        <h1>Bad Request</h1>
                        Required parameter 'key' is missing
                    </body>
                </html>
                HTML,
            400,
        );
    }

    /**
     * A request that never reaches Steam, retried like a real outage and raised as
     * `SteamConnectionException` once the tries are spent. Saloon throws it before
     * recording a response, so `getRecordedResponses()` and the `assertSent` family
     * never see the attempt.
     */
    public static function connectionFailed(): MockResponse
    {
        return MockResponse::make()->throw(
            static fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(
                new ConnectException(
                    'cURL error 28: Operation timed out after 10000 milliseconds',
                    $pendingRequest->createPsrRequest(),
                ),
                $pendingRequest,
            ),
        );
    }

    /**
     * Steam throttling the key, raised as `SteamRateLimitException`. The lock it leaves
     * refuses later requests sent the same way for `Retry-After` seconds, or
     * `tooManyRequestsCooldown` without one.
     */
    public static function tooManyRequests(?int $retryAfter = null): MockResponse
    {
        return MockResponse::make([], 429, $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter]);
    }
}
