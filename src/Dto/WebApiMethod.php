<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class WebApiMethod
{
    /**
     * @param  list<WebApiParameter>  $parameters
     */
    public function __construct(
        public string $name,
        public int $version,
        public string $httpMethod,
        public ?string $description,
        public array $parameters,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     version: int,
     *     httpmethod: string,
     *     description?: string,
     *     parameters: list<array{name: string, type: string, optional: bool, description?: string}>,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            version: $payload['version'],
            httpMethod: $payload['httpmethod'],
            description: $payload['description'] ?? null,
            parameters: array_map(WebApiParameter::fromArray(...), $payload['parameters']),
        );
    }
}
