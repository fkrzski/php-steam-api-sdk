<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats;

use Fkrzski\SteamApiSdk\Dto\UserStat;

/**
 * Builds the payload Steam returns for one entry of `playerstats.stats`.
 */
final readonly class UserStatFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'DBD_KillerSkulls',
            'value' => 42,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function name(string $name): self
    {
        return $this->state(['name' => $name]);
    }

    public function value(int|float $value): self
    {
        return $this->state(['value' => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): UserStat
    {
        /** @var array{name: string, value: int|float} $payload */
        $payload = $this->attributes;

        return UserStat::fromArray($payload);
    }
}
