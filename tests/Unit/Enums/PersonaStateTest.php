<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\PersonaState;

test('all documented persona state values map to enum cases', function (int $value, PersonaState $expected): void {
    expect(PersonaState::from($value))->toBe($expected);
})->with([
    'offline' => [0, PersonaState::Offline],
    'online' => [1, PersonaState::Online],
    'busy' => [2, PersonaState::Busy],
    'away' => [3, PersonaState::Away],
    'snooze' => [4, PersonaState::Snooze],
    'looking to trade' => [5, PersonaState::LookingToTrade],
    'looking to play' => [6, PersonaState::LookingToPlay],
]);

test('unknown values throw ValueError', function (): void {
    PersonaState::from(99);
})->throws(ValueError::class);
