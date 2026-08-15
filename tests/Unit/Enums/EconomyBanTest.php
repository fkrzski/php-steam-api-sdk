<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\EconomyBan;

covers(EconomyBan::class);

test('maps API string values to enum cases', function (string $value, EconomyBan $expected): void {
    expect(EconomyBan::fromApiValue($value))->toBe($expected);
})->with([
    'none' => ['none', EconomyBan::None],
    'probation' => ['probation', EconomyBan::Probation],
    'banned' => ['banned', EconomyBan::Banned],
]);

test('unknown values degrade to Unknown instead of throwing', function (string $value): void {
    expect(EconomyBan::fromApiValue($value))->toBe(EconomyBan::Unknown);
})->with([
    'undocumented future state' => ['permanent'],
    'empty string' => [''],
    'wrong casing' => ['None'],
]);

test('backing values mirror the wire vocabulary and survive json_encode', function (): void {
    expect(EconomyBan::None->value)->toBe('none')
        ->and(EconomyBan::Probation->value)->toBe('probation')
        ->and(EconomyBan::Banned->value)->toBe('banned')
        ->and(EconomyBan::Unknown->value)->toBe('unknown')
        ->and(json_encode(EconomyBan::Banned, JSON_THROW_ON_ERROR))->toBe('"banned"');
});
