<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps;

use Fkrzski\SteamApiSdk\Dto\SdrRelay;

/**
 * Builds the payload Steam returns for one entry of a point of presence's
 * `relays` on `GetSDRConfig`.
 */
final readonly class SdrRelayFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'ipv4' => '155.133.248.36',
            'port_range' => [27015, 27060],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function ipv4(string $ipv4): self
    {
        return $this->state(['ipv4' => $ipv4]);
    }

    public function ports(int $minPort, int $maxPort): self
    {
        return $this->state(['port_range' => [$minPort, $maxPort]]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): SdrRelay
    {
        /** @var array{ipv4: string, port_range: array{int, int}} $payload */
        $payload = $this->attributes;

        return SdrRelay::fromArray($payload);
    }
}
