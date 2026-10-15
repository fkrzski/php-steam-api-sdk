<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class UserStatAchievement
{
    /** @internal */
    public function __construct(
        public string $name,
        public bool $achieved,
    ) {}

    /**
     * @internal
     *
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
