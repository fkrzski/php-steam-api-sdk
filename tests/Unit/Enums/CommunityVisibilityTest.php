<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;

covers(CommunityVisibility::class);

test('maps API values to enum cases', function (int $value, CommunityVisibility $expected): void {
    expect(CommunityVisibility::fromApiValue($value))->toBe($expected);
})->with([
    'hidden' => [1, CommunityVisibility::Hidden],
    'visible' => [3, CommunityVisibility::Visible],
]);

test('unknown values throw UnexpectedValueException', function (): void {
    CommunityVisibility::fromApiValue(2);
})->throws(UnexpectedValueException::class, 'Unknown communityvisibilitystate value "2".');

test('backing values mirror the API and survive json_encode', function (): void {
    expect(CommunityVisibility::Hidden->value)->toBe(1)
        ->and(CommunityVisibility::Visible->value)->toBe(3)
        ->and(json_encode(CommunityVisibility::Visible, JSON_THROW_ON_ERROR))->toBe('3');
});
