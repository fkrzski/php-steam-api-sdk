<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

/**
 * Builds the payload Steam returns for one entry of `GetFriendList`.
 */
final readonly class FriendFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'steamid' => '76561198000000001',
            'relationship' => FriendRelationship::Friend->value,
            'friend_since' => 1600000000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function steamId(SteamId $steamId): self
    {
        return $this->state(['steamid' => $steamId->value]);
    }

    public function relationship(FriendRelationship $relationship): self
    {
        return $this->state(['relationship' => $relationship->value]);
    }

    public function since(DateTimeInterface $since): self
    {
        return $this->state(['friend_since' => $since->getTimestamp()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): Friend
    {
        /** @var array{steamid: string, relationship: string, friend_since: int} $payload */
        $payload = $this->attributes;

        return Friend::fromArray($payload);
    }
}
