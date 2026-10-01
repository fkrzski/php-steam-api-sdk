<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class SdrPointOfPresence
{
    /**
     * @param  list<string>  $aliases
     * @param  list<SdrRelay>  $relays
     */
    public function __construct(
        public string $code,
        public string $description,
        public float $latitude,
        public float $longitude,
        public array $aliases,
        public array $relays,
    ) {}

    /**
     * Valve sends `geo` as [longitude, latitude].
     *
     * @param  array{
     *     desc: string,
     *     geo: array{int|float, int|float},
     *     aliases?: list<string>,
     *     relays?: list<array{ipv4: string, port_range: array{int, int}}>,
     * }  $payload
     */
    public static function fromArray(string $code, array $payload): self
    {
        return new self(
            code: $code,
            description: $payload['desc'],
            latitude: $payload['geo'][1],
            longitude: $payload['geo'][0],
            aliases: $payload['aliases'] ?? [],
            relays: array_map(SdrRelay::fromArray(...), $payload['relays'] ?? []),
        );
    }
}
