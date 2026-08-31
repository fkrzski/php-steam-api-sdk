<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class SchemaAchievement
{
    public function __construct(
        public string $apiName,
        public string $name,
        public ?string $description,
        public bool $hidden,
        public string $icon,
        public string $iconGray,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     displayName: string,
     *     hidden: int,
     *     description?: string,
     *     icon: string,
     *     icongray: string,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            apiName: $payload['name'],
            name: $payload['displayName'],
            description: $payload['description'] ?? null,
            hidden: $payload['hidden'] === 1,
            icon: $payload['icon'],
            iconGray: $payload['icongray'],
        );
    }
}
