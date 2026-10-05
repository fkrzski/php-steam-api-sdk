<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;
use Saloon\Helpers\Debugger;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Http\Senders\GuzzleSender;
use Symfony\Component\VarDumper\VarDumper;

covers([SteamConnector::class]);

afterEach(function (): void {
    VarDumper::setHandler(null);
    Debugger::$dieHandler = null;
});

function debuggableConnector(): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig('secret-key'));
    $connector->withMockClient(new MockClient([MockResponse::make()]));

    return $connector;
}

/**
 * @return list<string>
 */
function debuggedUris(Request $request): array
{
    $uris = [];

    debuggableConnector()
        ->debugRequest(static function (PendingRequest $pendingRequest, RequestInterface $psrRequest) use (&$uris): void {
            $uris[] = (string) $psrRequest->getUri();
        })
        ->send($request);

    return $uris;
}

test('hands the request debugger a copy with the key masked', function (): void {
    expect(debuggedUris(friendListRequest()))
        ->toBe(['https://api.steampowered.com/ISteamUser/GetFriendList/v1/?key=***&steamid=76561198148125221']);
});

test('leaves a request Steam serves anonymously untouched', function (): void {
    expect(debuggedUris(new GetNumberOfCurrentPlayersRequest(440)))
        ->toBe(['https://api.steampowered.com/ISteamUserStats/GetNumberOfCurrentPlayers/v1/?appid=440']);
});

test('keeps the key on the request Steam receives', function (): void {
    $history = [];
    $connector = connectorAnswering([new PsrResponse(200, [], '{"friendslist":{"friends":[]}}')], new SteamConfig('secret-key'));
    $sender = $connector->sender();

    assert($sender instanceof GuzzleSender);

    $sender->getHandlerStack()->push(Middleware::history($history));

    $connector->debugRequest(static function (): void {})->send(friendListRequest());

    expect($history)->toHaveCount(1)
        ->and($history[0]['request']->getUri()->getQuery())->toBe('key=secret-key&steamid=76561198148125221');
});

test('hands a failed answer to the response debugger', function (Closure $send): void {
    $statuses = [];
    $connector = connectorAnswering([new PsrResponse(503)])
        ->debugResponse(static function (Response $response) use (&$statuses): void {
            $statuses[] = $response->status();
        });

    expect(fn (): mixed => $send($connector))->toThrow(SteamApiException::class)
        ->and($statuses)->toBe([503]);
})->with([
    'send' => [static fn (SteamConnector $connector): mixed => $connector->send(friendListRequest())],
    'sendAsync' => [static fn (SteamConnector $connector): mixed => $connector->sendAsync(friendListRequest())->wait()],
]);

test('masks the key in the default dump of debug()', function (): void {
    $dumps = [];

    VarDumper::setHandler(static function (mixed $dump) use (&$dumps): void {
        $dumps[] = $dump;
    });

    debuggableConnector()->debug()->send(friendListRequest());

    expect($dumps)->toHaveCount(2)
        ->and($dumps[0]['uri'])->toBe('https://api.steampowered.com/ISteamUser/GetFriendList/v1/?key=***&steamid=76561198148125221')
        ->and(json_encode($dumps))->not->toContain('secret-key');
});

test('stops after the dump when asked to', function (): void {
    $stopped = false;

    Debugger::$dieHandler = static function () use (&$stopped): void {
        $stopped = true;
    };

    debuggableConnector()->debugRequest(static function (): void {}, die: true)->send(friendListRequest());

    expect($stopped)->toBeTrue();
});
