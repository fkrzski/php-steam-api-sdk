<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http;

use Psr\Http\Message\UriInterface;
use Saloon\Http\PendingRequest;

/**
 * Every form the API key leaves the SDK in; a new output that could carry it goes through here.
 *
 * @internal
 */
final class ApiKeyMask
{
    private const string PARAMETER = 'key';

    private const string MASK = '***';

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function query(array $query): array
    {
        unset($query[self::PARAMETER]);

        return $query;
    }

    public static function uri(UriInterface $uri): UriInterface
    {
        return $uri->withQuery(implode('&', array_map(
            static fn (string $pair): string => str_starts_with($pair, self::PARAMETER.'=') ? self::PARAMETER.'='.self::MASK : $pair,
            explode('&', $uri->getQuery()),
        )));
    }

    /**
     * Raw as configured, and URL-encoded as the URI carries it and Guzzle 7 quotes it.
     */
    public static function message(string $message, PendingRequest $pendingRequest): string
    {
        $key = $pendingRequest->query()->get(self::PARAMETER);

        return is_string($key) ? str_replace([urlencode($key), $key], self::MASK, $message) : $message;
    }

    public static function digest(string $key): string
    {
        return hash('sha256', $key);
    }
}
