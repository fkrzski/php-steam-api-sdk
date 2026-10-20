<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser;

use Fkrzski\SteamApiSdk\Dto\UserGroup;

/**
 * Builds the payload Steam returns for one entry of `GetUserGroupList`.
 */
final readonly class UserGroupFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'gid' => '4218398',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function gid(string $gid): self
    {
        return $this->state(['gid' => $gid]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): UserGroup
    {
        /** @var array{gid: string} $payload */
        $payload = $this->attributes;

        return UserGroup::fromArray($payload);
    }
}
