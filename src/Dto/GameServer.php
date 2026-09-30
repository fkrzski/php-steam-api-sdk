<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use Fkrzski\SteamApiSdk\Enums\ServerRegion;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

final readonly class GameServer
{
    public function __construct(
        public string $address,
        public SteamId $steamId,
        public int $appId,
        public string $gameDir,
        public ServerRegion $region,
        public bool $isSecure,
        public bool $isLan,
        public int $gamePort,
        public ?int $spectatorPort,
    ) {}

    /**
     * @param  array{
     *     addr: string,
     *     steamid: string,
     *     appid: int,
     *     gamedir: string,
     *     region: int,
     *     secure: bool,
     *     lan: bool,
     *     gameport: int,
     *     specport: int,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            address: $payload['addr'],
            steamId: SteamId::fromSteamId64($payload['steamid']),
            appId: $payload['appid'],
            gameDir: $payload['gamedir'],
            region: ServerRegion::fromApiValue($payload['region']),
            isSecure: $payload['secure'],
            isLan: $payload['lan'],
            gamePort: $payload['gameport'],
            spectatorPort: $payload['specport'] !== 0 ? $payload['specport'] : null,
        );
    }
}
