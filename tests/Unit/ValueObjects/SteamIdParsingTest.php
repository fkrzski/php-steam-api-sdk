<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\InvalidSteamIdException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

covers(SteamId::class, InvalidSteamIdException::class);

test('fromSteamId64 accepts a 17-digit numeric string', function (): void {
    $id = SteamId::fromSteamId64('76561198000000000');

    expect($id->value)->toBe('76561198000000000')
        ->and((string) $id)->toBe('76561198000000000');
});

test('fromSteamId64 rejects invalid input', function (string $value): void {
    SteamId::fromSteamId64($value);
})
    ->with([
        'too short' => ['7656119800000000'],
        'too long' => ['765611980000000000'],
        'with letters' => ['7656119800000000a'],
        'empty' => [''],
        'whitespace' => ['                 '],
    ])
    ->throws(InvalidSteamIdException::class);

test('tryFromInput parses numeric and profile URL inputs', function (string $input, ?string $expected): void {
    $result = SteamId::tryFromInput($input);

    if ($expected === null) {
        expect($result)->toBeNull();
    } else {
        expect($result)->not->toBeNull()
            ->and($result?->value)->toBe($expected);
    }
})->with([
    'plain 17-digit' => ['76561198000000000', '76561198000000000'],
    'profile URL' => ['https://steamcommunity.com/profiles/76561198000000000', '76561198000000000'],
    'profile URL trailing slash' => ['https://steamcommunity.com/profiles/76561198000000000/', '76561198000000000'],
    'whitespace padded numeric' => ['  76561198000000000  ', '76561198000000000'],
    'vanity URL → null' => ['https://steamcommunity.com/id/gabelogannewell', null],
    'bare vanity → null' => ['gabelogannewell', null],
    'empty → null' => ['', null],
    'too short numeric → null' => ['1234567890', null],
]);

test('extractVanityName pulls slug from id-URL, else returns input', function (string $input, string $expected): void {
    expect(SteamId::extractVanityName($input))->toBe($expected);
})->with([
    'vanity URL' => ['https://steamcommunity.com/id/gabelogannewell', 'gabelogannewell'],
    'vanity URL trailing slash' => ['https://steamcommunity.com/id/gabelogannewell/', 'gabelogannewell'],
    'bare nick' => ['gabelogannewell', 'gabelogannewell'],
    'nick with spaces' => ['  someUser  ', 'someUser'],
]);

test('equals compares by value', function (): void {
    $a = SteamId::fromSteamId64('76561198000000000');
    $b = SteamId::fromSteamId64('76561198000000000');
    $c = SteamId::fromSteamId64('76561198000000001');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});
