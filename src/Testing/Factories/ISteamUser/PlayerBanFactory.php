<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser;

use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Enums\EconomyBan;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

/**
 * Builds the payload Steam returns for one entry of `GetPlayerBans`, which is
 * the one endpoint keying its Steam ID as `SteamId` rather than `steamid`.
 *
 * Defaults describe an account with a clean record.
 */
final readonly class PlayerBanFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'SteamId' => '76561198000000000',
            'CommunityBanned' => false,
            'VACBanned' => false,
            'NumberOfVACBans' => 0,
            'DaysSinceLastBan' => 0,
            'NumberOfGameBans' => 0,
            'EconomyBan' => EconomyBan::None->value,
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
        return $this->state(['SteamId' => $steamId->value]);
    }

    public function vacBanned(int $count = 1, int $daysSinceLastBan = 30): self
    {
        return $this->state([
            'VACBanned' => true,
            'NumberOfVACBans' => $count,
            'DaysSinceLastBan' => $daysSinceLastBan,
        ]);
    }

    public function gameBanned(int $count = 1, int $daysSinceLastBan = 30): self
    {
        return $this->state([
            'NumberOfGameBans' => $count,
            'DaysSinceLastBan' => $daysSinceLastBan,
        ]);
    }

    public function communityBanned(): self
    {
        return $this->state(['CommunityBanned' => true]);
    }

    public function economyBan(EconomyBan $economyBan): self
    {
        return $this->state(['EconomyBan' => $economyBan->value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): PlayerBan
    {
        /**
         * @var array{
         *     SteamId: string,
         *     CommunityBanned: bool,
         *     VACBanned: bool,
         *     NumberOfVACBans: int,
         *     DaysSinceLastBan: int,
         *     NumberOfGameBans: int,
         *     EconomyBan: string,
         * } $payload
         */
        $payload = $this->attributes;

        return PlayerBan::fromArray($payload);
    }
}
