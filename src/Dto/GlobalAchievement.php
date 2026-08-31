<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class GlobalAchievement
{
    public function __construct(
        public string $apiName,
        public float $percent,
    ) {}

    /**
     * @param  array{name: string, percent: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            apiName: $payload['name'],
            percent: (float) $payload['percent'],
        );
    }
}
