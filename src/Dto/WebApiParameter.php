<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class WebApiParameter
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $isOptional,
        public ?string $description,
    ) {}

    /**
     * Steam leaves the description out on some parameters and sends it empty on others.
     *
     * @param  array{name: string, type: string, optional: bool, description?: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $description = $payload['description'] ?? '';

        return new self(
            name: $payload['name'],
            type: $payload['type'],
            isOptional: $payload['optional'],
            description: $description !== '' ? $description : null,
        );
    }
}
