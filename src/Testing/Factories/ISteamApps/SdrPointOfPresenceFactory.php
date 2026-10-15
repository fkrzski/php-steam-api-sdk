<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps;

use Fkrzski\SteamApiSdk\Dto\SdrPointOfPresence;

/**
 * Builds the payload Steam returns for one entry of `pops` on `GetSDRConfig`.
 *
 * The code is the key Steam files the entry under, so it stays out of the
 * payload. Defaults describe Amsterdam with a single relay.
 */
final readonly class SdrPointOfPresenceFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(public string $code, private array $attributes) {}

    public static function new(): self
    {
        return new self('ams', [
            'desc' => 'Amsterdam (Netherlands)',
            'geo' => [4.9, 52.37],
            'relays' => [SdrRelayFactory::new()->toArray()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self($this->code, [...$this->attributes, ...$overrides]);
    }

    public function code(string $code): self
    {
        return new self($code, $this->attributes);
    }

    public function description(string $description): self
    {
        return $this->state(['desc' => $description]);
    }

    /**
     * Valve sends `geo` as [longitude, latitude], so this writes the second slot.
     */
    public function latitude(float $latitude): self
    {
        return $this->state(['geo' => [$this->geo()[0], $latitude]]);
    }

    /**
     * Valve sends `geo` as [longitude, latitude], so this writes the first slot.
     */
    public function longitude(float $longitude): self
    {
        return $this->state(['geo' => [$longitude, $this->geo()[1]]]);
    }

    public function aliases(string ...$aliases): self
    {
        return $this->state(['aliases' => $aliases]);
    }

    public function relays(SdrRelayFactory ...$relays): self
    {
        return $this->state([
            'relays' => array_map(
                static fn (SdrRelayFactory $relay): array => $relay->toArray(),
                $relays,
            ),
        ]);
    }

    /**
     * A point of presence with no relays, which Steam sends by dropping the key
     * rather than as an empty list.
     */
    public function withoutRelays(): self
    {
        $attributes = $this->attributes;

        unset($attributes['relays']);

        return new self($this->code, $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): SdrPointOfPresence
    {
        /**
         * @var array{
         *     desc: string,
         *     geo: array{int|float, int|float},
         *     aliases?: list<string>,
         *     relays?: list<array{ipv4: string, port_range: array{int, int}}>,
         * } $payload
         */
        $payload = $this->attributes;

        return SdrPointOfPresence::fromArray($this->code, $payload);
    }

    /**
     * @return array{int|float, int|float}
     */
    private function geo(): array
    {
        /** @var array{int|float, int|float} $geo */
        $geo = $this->attributes['geo'];

        return $geo;
    }
}
