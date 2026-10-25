<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps;

use Fkrzski\SteamApiSdk\Dto\SdrConfig;

/**
 * Builds the payload Steam returns for `GetSDRConfig`, less the `success` flag
 * `AppsFake::sdrConfig()` adds.
 */
final readonly class SdrConfigFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        $pointOfPresence = SdrPointOfPresenceFactory::new();

        return new self([
            'revision' => 1790374330,
            'pops' => [$pointOfPresence->code => $pointOfPresence->toArray()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function revision(int $revision): self
    {
        return $this->state(['revision' => $revision]);
    }

    /**
     * Steam keys `pops` by code, so each one lands under its own.
     */
    public function pointsOfPresence(SdrPointOfPresenceFactory ...$pointsOfPresence): self
    {
        $pops = [];

        foreach ($pointsOfPresence as $pointOfPresence) {
            $pops[$pointOfPresence->code] = $pointOfPresence->toArray();
        }

        return $this->state(['pops' => $pops]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): SdrConfig
    {
        /**
         * @var array{
         *     revision: int,
         *     pops: array<string, array{
         *         desc: string,
         *         geo: array{int|float, int|float},
         *         aliases?: list<string>,
         *         relays?: list<array{ipv4: string, port_range: array{int, int}}>,
         *     }>,
         * } $payload
         */
        $payload = $this->attributes;

        return SdrConfig::fromArray($payload);
    }
}
