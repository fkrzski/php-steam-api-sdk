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

test('unknown values throw UnexpectedValueException', function (): void {
    CommentPermission::fromApiValue(7);
})->throws(UnexpectedValueException::class, 'Unknown commentpermission value "7".');
