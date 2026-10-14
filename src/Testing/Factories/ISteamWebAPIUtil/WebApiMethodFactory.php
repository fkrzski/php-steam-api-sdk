<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil;

use Fkrzski\SteamApiSdk\Dto\WebApiMethod;

/**
 * Builds the payload Steam returns for one entry of an interface's `methods` on
 * `GetSupportedAPIList`.
 */
final readonly class WebApiMethodFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'GetPasswordRSAPublicKey',
            'version' => 1,
            'httpmethod' => 'GET',
            'description' => 'Fetches RSA public key to use to encrypt passwords for a given account name',
            'parameters' => [WebApiParameterFactory::new()->toArray()],
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

    public function version(int $version): self
    {
        return $this->state(['version' => $version]);
    }

    public function post(): self
    {
        return $this->state(['httpmethod' => 'POST']);
    }

    public function description(string $description): self
    {
        return $this->state(['description' => $description]);
    }

    /**
     * Steam drops the description on most methods rather than sending it empty.
     */
    public function withoutDescription(): self
    {
        $attributes = $this->attributes;

        unset($attributes['description']);

        return new self($attributes);
    }

    /**
     * Steam sends the key on every method, as an empty list when it takes none.
     */
    public function parameters(WebApiParameterFactory ...$parameters): self
    {
        return $this->state([
            'parameters' => array_map(
                static fn (WebApiParameterFactory $parameter): array => $parameter->toArray(),
                $parameters,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): WebApiMethod
    {
        /**
         * @var array{
         *     name: string,
         *     version: int,
         *     httpmethod: string,
         *     description?: string,
         *     parameters: list<array{name: string, type: string, optional: bool, description?: string}>,
         * } $payload
         */
        $payload = $this->attributes;

        return WebApiMethod::fromArray($payload);
    }
}
