<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil;

use Fkrzski\SteamApiSdk\Dto\WebApiInterface;

/**
 * Builds the payload Steam returns for one entry of `apilist.interfaces` on
 * `GetSupportedAPIList`.
 */
final readonly class WebApiInterfaceFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'name' => 'IAuthenticationService',
            'methods' => [WebApiMethodFactory::new()->toArray()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    /**
     * Steam suffixes some names with an app ID, as in `IGCVersion_440`, so any string goes.
     */
    public function name(string $name): self
    {
        return $this->state(['name' => $name]);
    }

    public function methods(WebApiMethodFactory ...$methods): self
    {
        return $this->state([
            'methods' => array_map(
                static fn (WebApiMethodFactory $method): array => $method->toArray(),
                $methods,
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

    public function make(): WebApiInterface
    {
        /**
         * @var array{
         *     name: string,
         *     methods: list<array{
         *         name: string,
         *         version: int,
         *         httpmethod: string,
         *         description?: string,
         *         parameters: list<array{name: string, type: string, optional: bool, description?: string}>,
         *     }>,
         * } $payload
         */
        $payload = $this->attributes;

        return WebApiInterface::fromArray($payload);
    }
}
