<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\PersonaState;

covers(PersonaState::class);

test('maps API values to enum cases', function (int $value, PersonaState $expected): void {
    expect(PersonaState::fromApiValue($value))->toBe($expected);
})->with([
    'offline' => [0, PersonaState::Offline],
    'online' => [1, PersonaState::Online],
    'busy' => [2, PersonaState::Busy],
    'away' => [3, PersonaState::Away],
    'snooze' => [4, PersonaState::Snooze],
    'looking to trade' => [5, PersonaState::LookingToTrade],
    'looking to play' => [6, PersonaState::LookingToPlay],
    'invisible' => [7, PersonaState::Invisible],
]);

test('unlisted values degrade to Unknown instead of throwing', function (int $value): void {
    expect(PersonaState::fromApiValue($value))->toBe(PersonaState::Unknown);
})->with([
    'past the last state' => [8],
    'far past the last state' => [99],
    'negative' => [-2],
]);

test('backing values mirror the API and survive json_encode', function (): void {
    expect(PersonaState::Invisible->value)->toBe(7)
        ->and(PersonaState::Unknown->value)->toBe(-1)
        ->and(json_encode(PersonaState::Invisible, JSON_THROW_ON_ERROR))->toBe('7');
});
