<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps;

use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;

/**
 * Builds the `response` payload Steam returns for `UpToDateCheck`, less the
 * `success` flag `AppsFake::upToDateCheck()` adds.
 *
 * Defaults describe a server running the current version.
 */
final readonly class AppVersionCheckFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'up_to_date' => true,
            'version_is_listable' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    /**
     * The message is free text each game writes — CS2 puts the version it wants there.
     */
    public function outOfDate(
        int $requiredVersion = 10828683,
        string $message = 'Your server is out of date, please upgrade',
    ): self {
        return $this->state([
            'up_to_date' => false,
            'version_is_listable' => false,
            'required_version' => $requiredVersion,
            'message' => $message,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): AppVersionCheck
    {
        /**
         * @var array{
         *     up_to_date: bool,
         *     version_is_listable: bool,
         *     required_version?: int,
         *     message?: string,
         * } $payload
         */
        $payload = $this->attributes;

        return AppVersionCheck::fromArray($payload);
    }
}
