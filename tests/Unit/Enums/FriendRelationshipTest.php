<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\FriendRelationship;

covers(FriendRelationship::class);

test('all documented relationship values map to enum cases', function (string $value, FriendRelationship $expected): void {
    expect(FriendRelationship::from($value))->toBe($expected);
})->with([
    'all' => ['all', FriendRelationship::All],
    'friend' => ['friend', FriendRelationship::Friend],
]);

test('unknown values throw ValueError', function (): void {
    FriendRelationship::from('unknown');
})->throws(ValueError::class);
