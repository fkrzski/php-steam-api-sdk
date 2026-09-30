<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\RequestOptions;

covers([SteamConnector::class]);

/**
 * @return array<string, mixed>
 */
function timeoutOptions(SteamConfig $config): array
{
    $request = new GetFriendListRequest(SteamId::fromSteamId64('76561198148125221'));

    return new SteamConnector($config)->createPendingRequest($request)->config()->all();
}

test('the connector sends the timeouts from the config', function (): void {
    $options = timeoutOptions(new SteamConfig('any', connectTimeout: 2.5, requestTimeout: 90));

    expect($options[RequestOptions::CONNECT_TIMEOUT])->toBe(2.5)
        ->and($options[RequestOptions::TIMEOUT])->toBe(90.0);
});

test('the connector falls back to Saloon timeouts when the config sets none', function (): void {
    $options = timeoutOptions(new SteamConfig('any'));

    expect($options[RequestOptions::CONNECT_TIMEOUT])->toBe(10.0)
        ->and($options[RequestOptions::TIMEOUT])->toBe(30.0);
});
