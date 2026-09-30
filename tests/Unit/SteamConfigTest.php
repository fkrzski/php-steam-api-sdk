<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRetryException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidTimeoutException;
use Fkrzski\SteamApiSdk\SteamConfig;

covers([SteamConfig::class, ApiKeyNotConfiguredException::class, InvalidRetryException::class, InvalidTimeoutException::class]);

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

test('SteamConfig carries no api key until one is passed', function (): void {
    expect((new SteamConfig)->apiKey)->toBeNull();
});

test('SteamConfig rejects an api key that holds no characters', function (string $apiKey): void {
    expect(fn (): SteamConfig => new SteamConfig(apiKey: $apiKey))
        ->toThrow(
            ApiKeyNotConfiguredException::class,
            'Steam API key is blank. Pass a real key to SteamConfig, or null to reach only the endpoints Steam serves anonymously.',
        );
})->with([
    'empty' => '',
    'whitespace' => "  \t ",
]);

test('SteamConfig leaves both timeouts to Saloon until they are set', function (): void {
    $config = new SteamConfig;

    expect($config->connectTimeout)->toBeNull()
        ->and($config->requestTimeout)->toBeNull();
});

test('SteamConfig stores both timeouts', function (): void {
    $config = new SteamConfig(connectTimeout: 2.5, requestTimeout: 60);

    expect($config->connectTimeout)->toBe(2.5)
        ->and($config->requestTimeout)->toBe(60.0);
});

test('SteamConfig accepts a zero timeout', function (): void {
    $config = new SteamConfig(connectTimeout: 0.0, requestTimeout: 0.0);

    expect($config->connectTimeout)->toBe(0.0)
        ->and($config->requestTimeout)->toBe(0.0);
});

test('SteamConfig rejects a negative timeout', function (string $option): void {
    expect(fn (): SteamConfig => new SteamConfig(...[$option => -0.5]))
        ->toThrow(
            InvalidTimeoutException::class,
            sprintf('SteamConfig::$%s cannot be negative, got -0.5. Pass seconds, 0 for no limit, or null for the default.', $option),
        );
})->with(['connectTimeout', 'requestTimeout']);

test('SteamConfig sends every request once until retries are configured', function (): void {
    $config = new SteamConfig;

    expect($config->tries)->toBe(1)
        ->and($config->retryInterval)->toBe(0)
        ->and($config->exponentialBackoff)->toBeFalse();
});

test('SteamConfig stores the retry settings', function (): void {
    $config = new SteamConfig(tries: 3, retryInterval: 500, exponentialBackoff: true);

    expect($config->tries)->toBe(3)
        ->and($config->retryInterval)->toBe(500)
        ->and($config->exponentialBackoff)->toBeTrue();
});

test('SteamConfig accepts a single try with no pause', function (): void {
    $config = new SteamConfig(tries: 1, retryInterval: 0);

    expect($config->tries)->toBe(1)
        ->and($config->retryInterval)->toBe(0);
});

test('SteamConfig rejects fewer than one try', function (int $tries): void {
    expect(fn (): SteamConfig => new SteamConfig(tries: $tries))
        ->toThrow(
            InvalidRetryException::class,
            sprintf('SteamConfig::$tries must be at least 1, got %d. Pass the total number of attempts, 1 to never retry.', $tries),
        );
})->with(['zero' => 0, 'negative' => -1]);

test('SteamConfig rejects a negative retry interval', function (): void {
    expect(fn (): SteamConfig => new SteamConfig(retryInterval: -1))
        ->toThrow(
            InvalidRetryException::class,
            'SteamConfig::$retryInterval cannot be negative, got -1. Pass milliseconds, 0 for no pause between attempts.',
        );
});
