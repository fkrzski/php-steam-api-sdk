<?php

declare(strict_types=1);

dataset('resource methods', function (): iterable {
    foreach (glob(__DIR__.'/../../src/Http/Resources/*Resource.php') ?: [] as $file) {
        $resource = 'Fkrzski\SteamApiSdk\Http\Resources\\'.basename($file, '.php');
        $fake = 'Fkrzski\SteamApiSdk\Testing\Fakes\\'.basename($file, 'Resource.php').'Fake';

        foreach (new ReflectionClass($resource)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $resource) {
                yield "{$resource}::{$method->getName()}" => [$fake, $method->getName()];
            }
        }
    }
});

test('every resource method has a fake of the same name', function (string $fake, string $method): void {
    expect(method_exists($fake, $method))->toBeTrue();
})->with('resource methods');
