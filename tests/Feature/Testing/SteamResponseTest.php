<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetCommunityBadgeProgressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers(SteamResponse::class);

function fakedId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function bodyOf(MockResponse $response): mixed
{
    return $response->body()->all();
}

// Envelopes

test('sends the body steam was captured sending', function (Closure $builder, string $fixture): void {
    /** @var object{statusCode: int, data: mixed} $captured */
    $captured = json_decode((string) file_get_contents(__DIR__."/../../Fixtures/Saloon/{$fixture}.json"), flags: JSON_THROW_ON_ERROR);

    expect($builder()->status())->toBe($captured->statusCode)
        ->and((string) $builder()->body())->toBe(is_string($captured->data) ? $captured->data : json_encode($captured->data, JSON_THROW_ON_ERROR));
})->with([
    'invalidApiKey()' => [SteamResponse::invalidApiKey(...), 'Errors/invalid-key'],
    'apiKeyUnauthorized()' => [SteamResponse::apiKeyUnauthorized(...), 'Errors/unauthorized-key'],
    'apiKeyMissing()' => [SteamResponse::apiKeyMissing(...), 'Errors/missing-key'],
]);

test('throttles with a 429 that carries Retry-After only when given one', function (): void {
    expect(SteamResponse::tooManyRequests()->status())->toBe(429)
        ->and(bodyOf(SteamResponse::tooManyRequests()))->toBe([])
        ->and(SteamResponse::tooManyRequests()->headers()->all())->toBe([])
        ->and(SteamResponse::tooManyRequests(120)->headers()->all())->toBe(['Retry-After' => '120']);
});

// Round trips through the connector

// Failures — these pin the contract with the SDK's exception mapping

test('raises an invalid api key from a rejected key', function (): void {
    $connector = fakedConnector(new MockClient([GetPlayerSummariesRequest::class => SteamResponse::invalidApiKey()]));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});

test('raises an invalid api key from a key rejected with a 401', function (): void {
    $connector = fakedConnector(new MockClient([GetCommunityBadgeProgressRequest::class => SteamResponse::apiKeyUnauthorized()]));

    expect(fn (): array => $connector->players()->communityBadgeProgress(fakedId()))
        ->toThrow(InvalidApiKeyException::class);
});

test('raises an invalid api key from a request sent without one', function (): void {
    $connector = fakedConnector(new MockClient([GetPlayerSummariesRequest::class => SteamResponse::apiKeyMissing()]));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))
        ->toThrow(InvalidApiKeyException::class);
});

test('raises a connection failure from a request that never reaches steam', function (): void {
    $connector = fakedConnector(new MockClient([GetPlayerSummariesRequest::class => SteamResponse::connectionFailed()]));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))->toThrow(
        SteamConnectionException::class,
        'Could not reach the Steam Web API: cURL error 28: Operation timed out after 10000 milliseconds',
    );
});

test('retries a connection failure without recording any attempt', function (): void {
    $attempts = 0;

    $mock = new MockClient([
        GetPlayerSummariesRequest::class => function () use (&$attempts): MockResponse {
            $attempts++;

            return SteamResponse::connectionFailed();
        },
    ]);
    $connector = fakedConnector($mock, new SteamConfig('test-key', tries: 3));

    expect(fn (): array => $connector->users()->summaries([fakedId()]))->toThrow(SteamConnectionException::class)
        ->and($attempts)->toBe(3)
        ->and($mock->getRecordedResponses())->toBeEmpty();
});

test('records no connection failure however it was sent', function (Closure $send): void {
    $mock = new MockClient([SteamResponse::connectionFailed()]);

    expect(failureFrom(fn (): mixed => $send(fakedConnector($mock))))->toBeInstanceOf(SteamConnectionException::class)
        ->and($mock->getRecordedResponses())->toBeEmpty();
})->with(everyWayToSend());

test('raises a rate limit from a 429 and holds the key for the cool-down or Retry-After', function (?int $retryAfter, int $seconds): void {
    $connector = fakedConnector(new MockClient([
        GetPlayerSummariesRequest::class => SteamResponse::tooManyRequests($retryAfter),
    ]));

    $before = time();

    expect(fn (): array => $connector->users()->summaries([fakedId()]))
        ->toThrow(function (SteamRateLimitException $exception) use ($before, $seconds): void {
            expect($exception->limit->getName())->toBe('SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:too_many_attempts_limit')
                ->and($exception->limit->getExpiryTimestamp())
                ->toBeGreaterThanOrEqual($before + $seconds)
                ->toBeLessThanOrEqual(time() + $seconds);
        });
})->with([
    'without Retry-After' => [null, 60],
    'with Retry-After' => [120, 120],
]);
