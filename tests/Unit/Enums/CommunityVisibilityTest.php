<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;

covers(CommunityVisibility::class);

test('maps API values to enum cases', function (int $value, CommunityVisibility $expected): void {
    expect(CommunityVisibility::fromApiValue($value))->toBe($expected);
})->with([
    'hidden' => [1, CommunityVisibility::Hidden],
    'friends only' => [2, CommunityVisibility::FriendsOnly],
    'visible' => [3, CommunityVisibility::Visible],
]);

test('unlisted values degrade to Unknown instead of throwing', function (int $value): void {
    expect(CommunityVisibility::fromApiValue($value))->toBe(CommunityVisibility::Unknown);
})->with([
    'zero' => [0],
    'past the last state' => [4],
]);

test('backing values mirror the API and survive json_encode', function (): void {
    expect(CommunityVisibility::Hidden->value)->toBe(1)
        ->and(CommunityVisibility::FriendsOnly->value)->toBe(2)
        ->and(CommunityVisibility::Visible->value)->toBe(3)
        ->and(CommunityVisibility::Unknown->value)->toBe(-1)
        ->and(json_encode(CommunityVisibility::Visible, JSON_THROW_ON_ERROR))->toBe('3');
});
