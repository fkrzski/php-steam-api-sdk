<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http;

use DateTimeImmutable;
use DateTimeZone;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\SteamConfig;
use Saloon\Enums\PipeOrder;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Helpers\RetryAfterHelper;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use SensitiveParameter;

/**
 * One budget Steam bills: the key's, or the anonymous one of requests that go out without it.
 *
 * @internal
 */
final class RateLimiter
{
    use HasRateLimits {
        bootHasRateLimits as private bootPlugin;
    }

    // LimitHelper::configureLimits() gives the plugin's own lock after a 429 this name.
    public const string TOO_MANY_ATTEMPTS_LIMIT = 'too_many_attempts_limit';

    /**
     * The answer this limiter judges, so it is built per request; the plugin hands throwLimitException() only the Limit.
     */
    private ?Response $answer = null;

    public function __construct(
        private readonly SteamConfig $config,
        #[SensitiveParameter]
        private readonly ?string $key,
    ) {}

    public function boot(PendingRequest $pendingRequest): void
    {
        $pendingRequest->middleware()->onResponse(function (Response $response): void {
            $this->answer = $response;
        }, order: PipeOrder::FIRST);

        $this->bootPlugin($pendingRequest);
    }

    /**
     * What the plugin's response pipe writes to the store, for an answer that never reached it; nothing is thrown or sent again.
     */
    public function record(Response $response): void
    {
        if ($response->getPendingRequest()->hasFakeResponse() && ! $response->isMocked()) {
            return;
        }

        $store = $this->rateLimitStore();

        foreach ($this->getLimits() as $limit) {
            $limit->update($store);

            if ($limit->usesResponse()) {
                $limit->handleResponse($response);
            } else {
                $limit->hit();
            }

            $limit->save($store);
        }
    }

    protected function resolveLimits(): array
    {
        if ($this->config->rateLimits !== null) {
            return $this->config->rateLimits;
        }

        if ($this->key === null) {
            return [];
        }

        // Not untilMidnightTonight(): that follows date.timezone, not UTC.
        return [
            Limit::allow(100_000)
                ->everySeconds(86_400, 'utc_midnight')
                ->setExpiryTimestamp(new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))->getTimestamp()),
        ];
    }

    /**
     * MemoryStore keeps its backing array static, so the default budget is shared
     * process-wide rather than per instance.
     */
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return $this->config->rateLimitStore ?? new MemoryStore;
    }

    /**
     * The quota belongs to the API key, not to the connector class. The key is hashed
     * so it never lands in a shared store.
     */
    protected function getLimiterPrefix(): string
    {
        if ($this->key === null) {
            return 'SteamConnector:anonymous';
        }

        return sprintf('SteamConnector:%s', ApiKeyMask::digest($this->key));
    }

    protected function throwLimitException(Limit $limit): never
    {
        throw SteamRateLimitException::fromLimit($limit, $this->answer);
    }

    /**
     * The plugin falls back to this interval only when a 429 carries no Retry-After.
     */
    protected function getTooManyAttemptsLimiter(): Limit
    {
        return Limit::custom($this->handleTooManyAttempts(...))->everySeconds($this->config->tooManyRequestsCooldown);
    }

    /**
     * The plugin sets no expiry for a Retry-After of 0 or in the past, so save() would fall back to the cool-down.
     */
    protected function handleTooManyAttempts(Response $response, Limit $limit): void
    {
        if ($response->status() !== 429) {
            return;
        }

        $seconds = RetryAfterHelper::parse($response->getPsrResponse()->getHeaderLine('Retry-After'));

        $limit->exceeded($seconds === null ? null : max($seconds, 1));
    }
}
