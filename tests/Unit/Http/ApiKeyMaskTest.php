<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\ApiKeyMask;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Psr7\Uri;

covers([ApiKeyMask::class]);

test('drops the key from a query and keeps the rest in order', function (): void {
    expect(ApiKeyMask::query(['steamid' => '1', 'key' => 'secret-key', 'l' => 'polish']))
        ->toBe(['steamid' => '1', 'l' => 'polish']);
});

test('masks the key pair of a URI and no other pair', function (string $uri, string $masked): void {
    expect((string) ApiKeyMask::uri(new Uri($uri)))->toBe($masked);
})->with([
    'among other pairs' => [
        'https://api.steampowered.com/ISteamUser/GetFriendList/v1/?steamid=1&key=secret+key%2B%2F&keyword=x',
        'https://api.steampowered.com/ISteamUser/GetFriendList/v1/?steamid=1&key=***&keyword=x',
    ],
    'absent' => [
        'https://api.steampowered.com/ISteamUserStats/GetNumberOfCurrentPlayers/v1/?appid=440',
        'https://api.steampowered.com/ISteamUserStats/GetNumberOfCurrentPlayers/v1/?appid=440',
    ],
]);

test('masks the key in a message, raw and URL-encoded', function (): void {
    $pendingRequest = new SteamConnector(new SteamConfig('secret key+/'))->createPendingRequest(friendListRequest());

    expect(ApiKeyMask::message('for ?key=secret+key%2B%2F (key=secret key+/)', $pendingRequest))
        ->toBe('for ?key=*** (key=***)');
});

test('leaves a message alone when the request carries no key', function (): void {
    $pendingRequest = new SteamConnector(new SteamConfig('secret key+/'))->createPendingRequest(new GetNumberOfCurrentPlayersRequest(440));

    expect(ApiKeyMask::message('for key=secret key+/', $pendingRequest))->toBe('for key=secret key+/');
});

test('keeps the digest rate limit stores already hold', function (): void {
    expect(ApiKeyMask::digest('test-key'))->toBe('62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef');
});
