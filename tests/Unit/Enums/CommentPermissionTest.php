<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\CommentPermission;

covers(CommentPermission::class);

test('maps API integer values to enum cases', function (?int $value, CommentPermission $expected): void {
    expect(CommentPermission::fromApiValue($value))->toBe($expected);
})->with([
    'everyone' => [1, CommentPermission::Everyone],
    'nobody' => [2, CommentPermission::Nobody],
    'friends only when key missing' => [null, CommentPermission::FriendsOnly],
]);

test('unlisted values degrade to Unknown instead of throwing', function (int $value): void {
    expect(CommentPermission::fromApiValue($value))->toBe(CommentPermission::Unknown);
})->with([
    'zero' => [0],
    'past the last value' => [3],
    'far past the last value' => [7],
]);

test('backing values are stable tokens and survive json_encode', function (): void {
    expect(CommentPermission::Everyone->value)->toBe('everyone')
        ->and(CommentPermission::Nobody->value)->toBe('nobody')
        ->and(CommentPermission::FriendsOnly->value)->toBe('friends_only')
        ->and(CommentPermission::Unknown->value)->toBe('unknown')
        ->and(json_encode(CommentPermission::FriendsOnly, JSON_THROW_ON_ERROR))->toBe('"friends_only"');
});
