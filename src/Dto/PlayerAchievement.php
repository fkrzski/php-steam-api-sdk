<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use DateTimeImmutable;

final readonly class PlayerAchievement
{
    public function __construct(
        public string $apiName,
        public bool $achieved,
        public ?DateTimeImmutable $unlockedAt,
        public ?string $name,
        public ?string $description,
    ) {}

    /**
     * @param  array{
     *     apiname: string,
     *     achieved: int,
     *     unlocktime: int,
     *     name?: string,
     *     description?: string,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            apiName: $payload['apiname'],
            achieved: $payload['achieved'] === 1,
            unlockedAt: $payload['unlocktime'] !== 0
                ? new DateTimeImmutable('@'.$payload['unlocktime'])
                : null,
            name: $payload['name'] ?? null,
            description: $payload['description'] ?? null,
        );
    }
}
