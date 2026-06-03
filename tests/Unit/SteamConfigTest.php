<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\SteamConfig;

test('SteamConfig stores api key', function (): void {
    $config = new SteamConfig(apiKey: 'test-key');

    expect($config->apiKey)->toBe('test-key');
});

test('SteamConfig is readonly', function (): void {
    $reflection = new ReflectionClass(SteamConfig::class);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($reflection->isFinal())->toBeTrue();
});
