<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http;

use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Saloon\RateLimitPlugin\Limit;

/**
 * What is left of the budget a connector checks, and how long until the next request may go.
 *
 * @since 1.1.0
 */
final readonly class RateLimitBudget
{
    /** @internal */
    public function __construct(
        private RateLimiter $limiter,
    ) {}

    /**
     * Each limit counting requests, keyed as in `rateLimits`, in the order the connector checks them.
     *
     * @return array<int|string, Allowance>
     */
    public function remaining(): array
    {
        $store = $this->limiter->rateLimitStore();

        return array_map(static function (Limit $limit) use ($store): Allowance {
            $limit->update($store);

            return new Allowance(
                allow: $limit->getAllow(),
                left: max(0, $limit->getAllow() - $limit->getHits()),
                window: $limit instanceof UtcDayLimit ? null : $limit->getReleaseInSeconds(),
                sleeps: $limit->getShouldSleep(),
            );
        }, $this->limiter->countingLimits());
    }

    /**
     * Seconds until the next request may go, or null while every limit has room.
     */
    public function wait(?float $threshold = null): ?int
    {
        $limit = $this->limiter->getExceededLimit($threshold);

        return $limit instanceof Limit ? $this->waitFor($limit) : null;
    }

    public function waitAfter(SteamRateLimitException $exception): int
    {
        return $this->waitFor($exception->limit);
    }

    // A closed window counts past zero, which would send a job back before the counter rolls over.
    private function waitFor(Limit $limit): int
    {
        return max(1, $limit->getRemainingSeconds());
    }
}
