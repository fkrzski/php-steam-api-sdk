<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRateLimitException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidRetryException;
use Fkrzski\SteamApiSdk\Exceptions\InvalidTimeoutException;
use Fkrzski\SteamApiSdk\SteamConfig;
use Saloon\Http\Senders\GuzzleSender;
use Saloon\RateLimitPlugin\Limit;

covers([SteamConfig::class, ApiKeyNotConfiguredException::class, InvalidRateLimitException::class, InvalidRetryException::class, InvalidTimeoutException::class]);

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

test('SteamConfig leaves the rate limits to the connector until they are set', function (): void {
    expect((new SteamConfig)->rateLimits)->toBeNull();
});

test('SteamConfig stores the rate limits it is given', function (array $rateLimits): void {
    expect((new SteamConfig(rateLimits: $rateLimits))->rateLimits)->toBe($rateLimits);
})->with([
    'none' => [[]],
    'one budget over two windows' => [[Limit::allow(10)->everyMinute(), Limit::allow(10)->everyHour()]],
]);

test('SteamConfig rejects two rate limits under one name', function (array $rateLimits, string $message): void {
    expect(fn (): SteamConfig => new SteamConfig(rateLimits: $rateLimits))
        ->toThrow(function (InvalidRateLimitException $invalidRateLimitException) use ($message): void {
            expect($invalidRateLimitException->getMessage())->toBe($message);
        });
})->with([
    'one window twice' => [
        [Limit::allow(10)->everyMinute(), Limit::allow(10)->everyHour(), Limit::allow(10)->everyMinute()->sleep()],
        'SteamConfig::$rateLimits[0] and [2] are both named "10_every_60". Give one of them a name of its own with ->name().',
    ],
    'one custom name' => [
        ['burst' => Limit::allow(5)->everySeconds(1)->name('burst'), 'steady' => Limit::allow(100)->everyMinute()->name('burst')],
        'SteamConfig::$rateLimits[burst] and [steady] are both named "burst". Give one of them a name of its own with ->name().',
    ],
    'two prefixes' => [
        [Limit::allow(10)->everyMinute()->setPrefix('first'), Limit::allow(10)->everyMinute()->setPrefix('second')],
        'SteamConfig::$rateLimits[0] and [1] are both named "10_every_60". Give one of them a name of its own with ->name().',
    ],
]);

test('SteamConfig leaves the rate limits it checks untouched', function (): void {
    $limit = Limit::allow(10)->everyMinute();

    new SteamConfig(rateLimits: [$limit]);

    expect($limit->getName())->toBe('saloon_rate_limiter:10_every_60');
});

test('SteamConfig leaves the sender to the connector until one is set', function (): void {
    expect((new SteamConfig)->sender)->toBeNull();
});

test('SteamConfig stores the sender it is given', function (): void {
    $sender = new GuzzleSender;

    expect((new SteamConfig(sender: $sender))->sender)->toBe($sender);
});

test('SteamConfig holds requests back for a minute after a 429 until told otherwise', function (): void {
    expect((new SteamConfig)->tooManyRequestsCooldown)->toBe(60);
});

test('SteamConfig stores the cool-down it is given', function (int $seconds): void {
    expect((new SteamConfig(tooManyRequestsCooldown: $seconds))->tooManyRequestsCooldown)->toBe($seconds);
})->with(['one second' => 1, 'five minutes' => 300]);

test('SteamConfig rejects a cool-down under a second', function (int $seconds): void {
    expect(fn (): SteamConfig => new SteamConfig(tooManyRequestsCooldown: $seconds))
        ->toThrow(function (InvalidRateLimitException $invalidRateLimitException) use ($seconds): void {
            expect($invalidRateLimitException->getMessage())->toBe(sprintf(
                'SteamConfig::$tooManyRequestsCooldown must be at least 1, got %d. Pass the seconds to hold requests back after a 429 that carries no Retry-After.',
                $seconds,
            ));
        });
})->with(['zero' => 0, 'negative' => -1]);

test('SteamConfig keeps the key out of the trace of a config error', function (): void {
    $ignoreArgs = (string) ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');

    try {
        new SteamConfig('secret-key', tries: 0);
    } catch (InvalidRetryException $invalidRetryException) {
        expect($invalidRetryException->getTraceAsString())
            ->toContain('SteamConfig->__construct(Object(SensitiveParameterValue), ')
            ->not->toContain('secret-key');

        return;
    } finally {
        ini_set('zend.exception_ignore_args', $ignoreArgs);
    }

    throw new RuntimeException('Expected the config to be rejected.');
});
