<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class UserStat
{
    public function __construct(
        public string $name,
        public int|float $value,
    ) {}

    /**
     * @param  array{name: string, value: int|float}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            value: $payload['value'],
        );
    }
}
