<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Saloon\Http\Senders\GuzzleSender;
use Saloon\MockConfig;

MockConfig::throwOnMissingFixtures();

/**
 * @return list<SteamId>
 */
function makeSteamIds(int $count): array
{
    $base = 76561198000000000;

    return array_map(
        static fn (int $offset): SteamId => SteamId::fromSteamId64((string) ($base + $offset)),
        range(0, $count - 1),
    );
}

/**
 * Saloon's MockResponse cannot raise a transport failure, so the queue is handed to
 * Guzzle's own handler underneath the sender instead.
 *
 * @param  list<PsrResponse|Throwable>  $queue
 */
function connectorAnswering(array $queue, SteamConfig $config = new SteamConfig('any')): SteamConnector
{
    $connector = new SteamConnector($config);
    $sender = $connector->sender();

    assert($sender instanceof GuzzleSender);

    $sender->getHandlerStack()->setHandler(new MockHandler($queue));

    return $connector;
}

function connectionFailure(string $message = 'cURL error 28: Operation timed out after 5000 milliseconds'): ConnectException
{
    return new ConnectException($message, new PsrRequest('GET', 'https://api.steampowered.com'));
}

function friendListRequest(): GetFriendListRequest
{
    return new GetFriendListRequest(SteamId::fromSteamId64('76561198148125221'));
}
