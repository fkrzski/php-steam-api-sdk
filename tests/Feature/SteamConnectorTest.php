<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

covers(SteamConnector::class);

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

test('resolveLimits exposes a single 100,000 requests-per-day limit', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    /** @var array<Limit> $limits */
    $limits = $connector->getLimits();

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->getAllow())->toBe(100_000);
});

test('resolveRateLimitStore returns the store configured on SteamConfig', function (): void {
    $store = new MemoryStore;
    $connector = new SteamConnector(new SteamConfig('any', $store));

    $rateLimitStore = $connector->rateLimitStore();

    expect($rateLimitStore)->toBe($store);
});

test('resolveRateLimitStore falls back to a fresh in-memory store', function (): void {
    $connector = new SteamConnector(new SteamConfig('any'));

    $rateLimitStore = $connector->rateLimitStore();

    expect($rateLimitStore)->toBeInstanceOf(MemoryStore::class);
});
