<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\UtcDayLimit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([UtcDayLimit::class]);

test('a day limit counts under one name whatever it allows', function (int $allow): void {
    $limit = UtcDayLimit::requests($allow);

    expect($limit->getName())->toBe('saloon_rate_limiter:utc_day')
        ->and($limit->getAllow())->toBe($allow)
        ->and($limit->getReleaseInSeconds())->toBe(86_400);
})->with(['the default budget' => 100_000, 'a bigger key' => 500_000]);

test('a day limit closes at the next midnight UTC whatever date.timezone says', function (string $timezone): void {
    $default = date_default_timezone_get();
    date_default_timezone_set($timezone);

    try {
        $expiry = UtcDayLimit::requests(5)->getExpiryTimestamp();
    } finally {
        date_default_timezone_set($default);
    }

    expect($expiry % 86_400)->toBe(0)
        ->and($expiry - time())->toBeGreaterThan(0)->toBeLessThanOrEqual(86_400);
})->with(['UTC', 'Pacific/Kiritimati', 'Pacific/Pago_Pago']);

test('a day limit keeps the end of the window its store holds', function (): void {
    $store = new MemoryStore;
    $limit = UtcDayLimit::requests(5);
    $end = time() + 60;
    $store->set($limit->getName(), json_encode(['timestamp' => $end, 'hits' => 2], JSON_THROW_ON_ERROR), 60);

    expect($limit->update($store)->getExpiryTimestamp())->toBe($end)
        ->and($limit->getHits())->toBe(2);
});
