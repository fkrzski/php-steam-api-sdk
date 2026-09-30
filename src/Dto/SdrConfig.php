<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class SdrConfig
{
    /**
     * @param  array<string, SdrPointOfPresence>  $pointsOfPresence
     */
    public function __construct(
        public int $revision,
        public array $pointsOfPresence,
    ) {}

    /**
     * @param  array{
     *     revision: int,
     *     pops: array<string, array{
     *         desc: string,
     *         geo: array{int|float, int|float},
     *         aliases?: list<string>,
     *         relays?: list<array{ipv4: string, port_range: array{int, int}}>,
     *     }>,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        $pointsOfPresence = [];

        foreach ($payload['pops'] as $code => $pop) {
            $pointsOfPresence[$code] = SdrPointOfPresence::fromArray($code, $pop);
        }

        return new self(
            revision: $payload['revision'],
            pointsOfPresence: $pointsOfPresence,
        );
    }
}
