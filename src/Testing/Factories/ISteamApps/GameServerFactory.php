<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps;

use Fkrzski\SteamApiSdk\Dto\GameServer;
use Fkrzski\SteamApiSdk\Enums\ServerRegion;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

/**
 * Builds the payload Steam returns for one entry of `GetServersAtAddress`.
 *
 * Defaults describe a VAC-secured TF2 server broadcasting SourceTV.
 */
final readonly class GameServerFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'addr' => '108.181.62.21:27015',
            'steamid' => '85568392924469984',
            'appid' => 440,
            'gamedir' => 'tf',
            'region' => ServerRegion::UsEast->value,
            'secure' => true,
            'lan' => false,
            'gameport' => 27015,
            'specport' => 27016,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function address(string $address): self
    {
        return $this->state(['addr' => $address]);
    }

    public function steamId(SteamId $steamId): self
    {
        return $this->state(['steamid' => $steamId->value]);
    }

    public function appId(int $appId): self
    {
        return $this->state(['appid' => $appId]);
    }

    public function region(ServerRegion $region): self
    {
        return $this->state(['region' => $region->value]);
    }

    public function insecure(): self
    {
        return $this->state(['secure' => false]);
    }

    /**
     * A server with SourceTV off, which Steam sends as a zero port rather than
     * omitting.
     */
    public function withoutSpectatorPort(): self
    {
        return $this->state(['specport' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): GameServer
    {
        /**
         * @var array{
         *     addr: string,
         *     steamid: string,
         *     appid: int,
         *     gamedir: string,
         *     region: int,
         *     secure: bool,
         *     lan: bool,
         *     gameport: int,
         *     specport: int,
         * } $payload
         */
        $payload = $this->attributes;

        return GameServer::fromArray($payload);
    }
}
