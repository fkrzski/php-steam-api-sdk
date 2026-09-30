<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class AppVersionCheck
{
    public function __construct(
        public bool $isUpToDate,
        public bool $isListable,
        public ?int $requiredVersion,
        public ?string $message,
    ) {}

    /**
     * @param  array{
     *     up_to_date: bool,
     *     version_is_listable: bool,
     *     required_version?: int,
     *     message?: string,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            isUpToDate: $payload['up_to_date'],
            isListable: $payload['version_is_listable'],
            requiredVersion: $payload['required_version'] ?? null,
            message: $payload['message'] ?? null,
        );
    }
}
