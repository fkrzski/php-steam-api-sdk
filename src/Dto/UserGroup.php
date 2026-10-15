<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class UserGroup
{
    /** @internal */
    public function __construct(
        public string $gid,
    ) {}

    /**
     * @internal
     *
     * @param  array{gid: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            gid: $payload['gid'],
        );
    }
}
