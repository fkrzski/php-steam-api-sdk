<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class SdrRelay
{
    public function __construct(
        public string $ipv4,
        public int $minPort,
        public int $maxPort,
    ) {}

    /**
     * @param  array{ipv4: string, port_range: array{int, int}}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            ipv4: $payload['ipv4'],
            minPort: $payload['port_range'][0],
            maxPort: $payload['port_range'][1],
        );
    }
}
