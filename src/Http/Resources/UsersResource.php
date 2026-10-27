<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Resources;

use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Generator;
use GuzzleHttp\Promise\Create;
use InvalidArgumentException;
use Saloon\Http\BaseResource;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class UsersResource extends BaseResource
{
    /**
     * Steam answers in no particular order and repeats a duplicated ID, so key the result by Steam ID.
     *
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerSummary>
     */
    public function summaries(array $steamIds, int $concurrency = 5): array
    {
        $requests = array_map(
            static fn (array $steamIds): GetPlayerSummariesRequest => new GetPlayerSummariesRequest($steamIds),
            $this->chunk($steamIds, GetPlayerSummariesRequest::MAX_STEAM_IDS),
        );

        return array_merge(...array_map(
            static fn (GetPlayerSummariesRequest $request, Response $response): array => $request->createDtoFromResponse($response),
            $requests,
            $this->sendAll($requests, $concurrency),
        ));
    }

    /**
     * Steam answers in no particular order and repeats a duplicated ID, so key the result by Steam ID.
     *
     * @param  list<SteamId>  $steamIds
     * @return list<PlayerBan>
     */
    public function bans(array $steamIds, int $concurrency = 5): array
    {
        $requests = array_map(
            static fn (array $steamIds): GetPlayerBansRequest => new GetPlayerBansRequest($steamIds),
            $this->chunk($steamIds, GetPlayerBansRequest::MAX_STEAM_IDS),
        );

        return array_merge(...array_map(
            static fn (GetPlayerBansRequest $request, Response $response): array => $request->createDtoFromResponse($response),
            $requests,
            $this->sendAll($requests, $concurrency),
        ));
    }

    /**
     * @return list<Friend>
     */
    public function friends(SteamId $steamId, ?FriendRelationship $relationship = null): array
    {
        $request = new GetFriendListRequest($steamId, $relationship);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    /**
     * @return list<UserGroup>
     */
    public function groups(SteamId $steamId): array
    {
        $request = new GetUserGroupListRequest($steamId);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    public function resolveVanityUrl(string $vanityName): SteamId
    {
        $request = new ResolveVanityUrlRequest($vanityName);

        return $request->createDtoFromResponse($this->connector->send($request));
    }

    /**
     * An empty list still makes one chunk, so the request rejects it.
     *
     * @param  list<SteamId>  $steamIds
     * @param  positive-int  $size
     * @return list<list<SteamId>>
     */
    private function chunk(array $steamIds, int $size): array
    {
        return array_chunk($steamIds, $size) ?: [[]];
    }

    /**
     * @param  list<Request>  $requests
     * @return list<Response>
     */
    private function sendAll(array $requests, int $concurrency): array
    {
        if ($concurrency < 1) {
            throw new InvalidArgumentException(sprintf('Concurrency must be at least 1, %d given.', $concurrency));
        }

        if (count($requests) === 1) {
            return [$this->connector->send($requests[0])];
        }

        $responses = [];
        $failure = null;

        $failed = static function (mixed $reason) use (&$failure): void {
            $failure ??= Create::exceptionFor($reason);
        };

        // Rejecting the pool would abandon the chunks in flight, which Steam still bills.
        $pending = (static function () use ($requests, &$failure): Generator {
            foreach ($requests as $key => $request) {
                if ($failure instanceof Throwable) {
                    return;
                }

                yield $key => $request;
            }
        })();

        $this->connector->pool(
            $pending,
            $concurrency,
            static function (Response $response, int|string $key) use (&$responses): void {
                $responses[$key] = $response;
            },
            $failed,
        )->send()->wait();

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return array_map(static fn (int $key): Response => $responses[$key], array_keys($requests));
    }
}
