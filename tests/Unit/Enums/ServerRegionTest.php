<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\ServerRegion;

covers(ServerRegion::class);

test('maps Steam region codes to enum cases', function (int $value, ServerRegion $expected): void {
    expect(ServerRegion::fromApiValue($value))->toBe($expected);
})->with([
    'world' => [-1, ServerRegion::World],
    'US east' => [0, ServerRegion::UsEast],
    'US west' => [1, ServerRegion::UsWest],
    'South America' => [2, ServerRegion::SouthAmerica],
    'Europe' => [3, ServerRegion::Europe],
    'Asia' => [4, ServerRegion::Asia],
    'Australia' => [5, ServerRegion::Australia],
    'Middle East' => [6, ServerRegion::MiddleEast],
    'Africa' => [7, ServerRegion::Africa],
]);

test('unlisted codes degrade to World instead of throwing', function (int $value): void {
    expect(ServerRegion::fromApiValue($value))->toBe(ServerRegion::World);
})->with([
    'master server spelling of the world' => [255],
    'past the last region' => [8],
    'below the world' => [-2],
]);
