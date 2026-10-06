<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Hooks;

final readonly class RequestSending
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function __construct(
        public string $method,
        public array $query,
        public int $attempt,
    ) {}
}
