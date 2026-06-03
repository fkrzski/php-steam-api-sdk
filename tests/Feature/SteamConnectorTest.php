<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

test('base URL resolves to Steam Web API host', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    expect($connector->resolveBaseUrl())->toBe('https://api.steampowered.com');
});

test('default query carries api key', function (): void {
    $connector = new SteamConnector(new SteamConfig('secret-key'));

    expect($connector->query()->all())->toBe(['key' => 'secret-key']);
});

test('connector uses AlwaysThrowOnErrors plugin', function (): void {
    expect(in_array(AlwaysThrowOnErrors::class, class_uses(SteamConnector::class), true))->toBeTrue();
});
