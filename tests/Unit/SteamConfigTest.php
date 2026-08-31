<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\SteamConfig;

covers(SteamConfig::class);

test('SteamConfig stores api key', function (): void {
    $config = new SteamConfig(apiKey: 'test-key');

    expect($config->apiKey)->toBe('test-key');
});

test('SteamConfig is readonly', function (): void {
    $reflection = new ReflectionClass(SteamConfig::class);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($reflection->isFinal())->toBeTrue();
});

test('SteamConfig has no default language until one is set', function (): void {
    $config = new SteamConfig(apiKey: 'test-key');

    expect($config->language)->toBeNull();
});

test('SteamConfig stores the default language', function (): void {
    $config = new SteamConfig(apiKey: 'test-key', language: Language::Polish);

    expect($config->language)->toBe(Language::Polish);
});
