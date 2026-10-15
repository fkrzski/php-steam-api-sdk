<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService;

use Fkrzski\SteamApiSdk\Dto\CommunityBadgeQuest;

/**
 * Builds the payload Steam returns for one entry of `GetCommunityBadgeProgress`.
 */
final readonly class CommunityBadgeQuestFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'questid' => 115,
            'completed' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function questId(int $questId): self
    {
        return $this->state(['questid' => $questId]);
    }

    public function incomplete(): self
    {
        return $this->state(['completed' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): CommunityBadgeQuest
    {
        /** @var array{questid: int, completed: bool} $payload */
        $payload = $this->attributes;

        return CommunityBadgeQuest::fromArray($payload);
    }
}
