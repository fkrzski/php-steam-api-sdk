<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser;

use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;
use Fkrzski\SteamApiSdk\Enums\PersonaState;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

/**
 * Builds the payload Steam returns for one entry of `GetPlayerSummaries`.
 *
 * Defaults describe a public profile that is offline and not in a game.
 */
final readonly class PlayerSummaryFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'steamid' => '76561198000000000',
            'personaname' => 'Gabe',
            'profileurl' => 'https://steamcommunity.com/id/gabelogannewell/',
            'avatar' => 'https://avatars.steamstatic.com/ee6b1c.jpg',
            'avatarmedium' => 'https://avatars.steamstatic.com/ee6b1c_medium.jpg',
            'avatarfull' => 'https://avatars.steamstatic.com/ee6b1c_full.jpg',
            'avatarhash' => 'ee6b1c0d1e3f2a4b5c6d7e8f9a0b1c2d3e4f5a6b',
            'communityvisibilitystate' => CommunityVisibility::Visible->value,
            'profilestate' => 1,
            'commentpermission' => 1,
            'personastate' => PersonaState::Offline->value,
            'realname' => 'Gabe Newell',
            'primaryclanid' => '103582791429521412',
            'timecreated' => 1063407589,
            'lastlogoff' => 1600000000,
            'loccountrycode' => 'US',
            'locstatecode' => 'WA',
            'loccityid' => 3961,
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

    public function personaName(string $personaName): self
    {
        return $this->state(['personaname' => $personaName]);
    }

    public function private(): self
    {
        $attributes = $this->attributes;

        unset(
            $attributes['realname'],
            $attributes['primaryclanid'],
            $attributes['timecreated'],
            $attributes['loccountrycode'],
            $attributes['locstatecode'],
            $attributes['loccityid'],
        );

        $attributes['communityvisibilitystate'] = CommunityVisibility::Hidden->value;

        return new self($attributes);
    }

    public function friendsOnly(): self
    {
        return $this->private()->state(['communityvisibilitystate' => CommunityVisibility::FriendsOnly->value]);
    }

    public function online(): self
    {
        return $this->state(['personastate' => PersonaState::Online->value]);
    }

    public function inGame(string $gameId = '381210', string $name = 'Dead by Daylight'): self
    {
        return $this->state([
            'gameid' => $gameId,
            'gameextrainfo' => $name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): PlayerSummary
    {
        /**
         * @var array{
         *     steamid: string,
         *     personaname: string,
         *     profileurl: string,
         *     avatar: string,
         *     avatarmedium: string,
         *     avatarfull: string,
         *     avatarhash: string,
         *     communityvisibilitystate: int,
         *     profilestate?: int,
         *     commentpermission?: int,
         *     personastate?: int,
         *     realname?: string,
         *     primaryclanid?: string,
         *     timecreated?: int,
         *     lastlogoff?: int,
         *     gameid?: string,
         *     gameextrainfo?: string,
         *     gameserverip?: string,
         *     loccountrycode?: string,
         *     locstatecode?: string,
         *     loccityid?: int,
         * } $payload
         */
        $payload = $this->attributes;

        return PlayerSummary::fromArray($payload);
    }
}
