<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;

test('maps API values to enum cases', function (int $value, CommunityVisibility $expected): void {
    expect(CommunityVisibility::fromApiValue($value))->toBe($expected);
})->with([
    'hidden' => [1, CommunityVisibility::Hidden],
    'visible' => [3, CommunityVisibility::Visible],
]);

test('unknown values throw UnexpectedValueException', function (): void {
    CommunityVisibility::fromApiValue(2);
})->throws(UnexpectedValueException::class, 'Unknown communityvisibilitystate value "2".');
