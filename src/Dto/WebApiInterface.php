<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class WebApiInterface
{
    /**
     * @param  list<WebApiMethod>  $methods
     */
    public function __construct(
        public string $name,
        public array $methods,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     methods: list<array{
     *         name: string,
     *         version: int,
     *         httpmethod: string,
     *         description?: string,
     *         parameters: list<array{name: string, type: string, optional: bool, description?: string}>,
     *     }>,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            methods: array_map(WebApiMethod::fromArray(...), $payload['methods']),
        );
    }
}
