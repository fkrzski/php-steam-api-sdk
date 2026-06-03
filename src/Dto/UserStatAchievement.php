<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class UserStatAchievement
{
    public function __construct(
        public string $name,
        public bool $achieved,
    ) {}

    /**
     * @param  array{name: string, achieved: int}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            achieved: $payload['achieved'] === 1,
        );
    }
}
