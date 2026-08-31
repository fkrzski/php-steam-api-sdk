<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class SchemaStat
{
    public function __construct(
        public string $apiName,
        public ?string $name,
        public int|float $defaultValue,
    ) {}

    /**
     * @param  array{name: string, defaultvalue: int|float, displayName: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            apiName: $payload['name'],
            name: $payload['displayName'] !== '' ? $payload['displayName'] : null,
            defaultValue: $payload['defaultvalue'],
        );
    }
}
