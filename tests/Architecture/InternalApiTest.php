<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Allowance;
use Fkrzski\SteamApiSdk\Http\RateLimitBudget;
use Fkrzski\SteamApiSdk\Http\Senders\SteamSender;

dataset('members the SDK builds', function (): iterable {
    $classesIn = static fn (string $namespace): array => array_map(
        static fn (string $file): string => sprintf('Fkrzski\SteamApiSdk\%s\%s', $namespace, basename($file, '.php')),
        glob(sprintf('%s/../../src/%s/*.php', __DIR__, $namespace)) ?: [],
    );

    foreach ($classesIn('Dto') as $class) {
        yield "{$class}::__construct" => [$class, '__construct'];
        yield "{$class}::fromArray" => [$class, 'fromArray'];
    }

    foreach ($classesIn('Hooks') as $class) {
        yield "{$class}::__construct" => [$class, '__construct'];
    }

    yield SteamSender::class.'::__construct' => [SteamSender::class, '__construct'];
    yield RateLimitBudget::class.'::__construct' => [RateLimitBudget::class, '__construct'];
    yield Allowance::class.'::__construct' => [Allowance::class, '__construct'];

    foreach ($classesIn('Exceptions') as $class) {
        foreach (new ReflectionClass($class)->getMethods(ReflectionMethod::IS_STATIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $class && ! $method->isPrivate()) {
                yield "{$class}::{$method->getName()}" => [$class, $method->getName()];
            }
        }
    }
});

test('members the SDK builds are internal', function (string $class, string $method): void {
    expect((string) new ReflectionMethod($class, $method)->getDocComment())->toContain('@internal');
})->with('members the SDK builds');

test('the root exception constructor stays public for custom exceptions', function (): void {
    expect((string) new ReflectionMethod(SteamApiException::class, '__construct')->getDocComment())->not->toContain('@internal');
});
