<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil;

use Fkrzski\SteamApiSdk\Dto\WebApiParameter;

/**
 * Builds the payload Steam returns for one entry of a method's `parameters` on
 * `GetSupportedAPIList`.
 */
final readonly class WebApiParameterFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'account_name',
            'type' => 'string',
            'optional' => false,
            'description' => 'user-provided account name to get an RSA key for',
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

    public function type(string $type): self
    {
        return $this->state(['type' => $type]);
    }

    public function optional(): self
    {
        return $this->state(['optional' => true]);
    }

    public function description(string $description): self
    {
        return $this->state(['description' => $description]);
    }

    /**
     * Steam drops the description on some parameters — see {@see self::emptyDescription()} for the rest.
     */
    public function withoutDescription(): self
    {
        $attributes = $this->attributes;

        unset($attributes['description']);

        return new self($attributes);
    }

    /**
     * Steam sends `''` on some parameters, which the DTO reads back as `null`, like a dropped key.
     */
    public function emptyDescription(): self
    {
        return $this->state(['description' => '']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): WebApiParameter
    {
        /** @var array{name: string, type: string, optional: bool, description?: string} $payload */
        $payload = $this->attributes;

        return WebApiParameter::fromArray($payload);
    }
}
