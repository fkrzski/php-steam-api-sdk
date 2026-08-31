<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\Language;

covers(Language::class);

test('backing values are Steam codes, not ISO ones', function (Language $language, string $expected): void {
    expect($language->value)->toBe($expected);
})->with([
    'simplified chinese' => [Language::ChineseSimplified, 'schinese'],
    'traditional chinese' => [Language::ChineseTraditional, 'tchinese'],
    'korean' => [Language::Korean, 'koreana'],
    'brazilian portuguese' => [Language::PortugueseBrazil, 'brazilian'],
    'latin american spanish' => [Language::SpanishLatinAmerican, 'latam'],
]);

test('the plain cases back onto their lowercased name', function (Language $language): void {
    expect($language->value)->toBe(strtolower($language->name));
})->with([
    'english' => [Language::English],
    'polish' => [Language::Polish],
    'japanese' => [Language::Japanese],
    'ukrainian' => [Language::Ukrainian],
]);

test('the enum transcribes the whole Steam table', function (): void {
    expect(Language::cases())->toHaveCount(30)
        ->and(Language::tryFrom('english'))->toBe(Language::English)
        ->and(Language::tryFrom('portuguese'))->toBe(Language::Portuguese)
        ->and(Language::tryFrom('pt-BR'))->toBeNull();
});

test('a case survives json_encode as its Steam code', function (): void {
    expect(json_encode(Language::Korean, JSON_THROW_ON_ERROR))->toBe('"koreana"');
});
