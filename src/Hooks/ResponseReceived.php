<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Hooks;

final readonly class ResponseReceived
{
    /**
     * @internal
     *
     * @param  array<string, mixed>  $query
     */
    public function __construct(
        public string $method,
        public array $query,
        public int $attempt,
        public int $status,
        public float $duration,
    ) {}
}
