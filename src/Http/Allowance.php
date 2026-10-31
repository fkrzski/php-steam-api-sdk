<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http;

/**
 * What is left of one limit counting requests.
 *
 * @since 1.1.0
 */
final readonly class Allowance
{
    /** @internal */
    public function __construct(
        public int $allow,
        public int $left,
        /** Seconds the window lasts, null for one closing at 00:00 UTC. */
        public ?int $window,
        public bool $sleeps,
    ) {}
}
