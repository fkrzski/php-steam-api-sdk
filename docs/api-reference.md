---
title: API reference
description: Every request the SDK ships — constructor signature, query parameters, the readonly DTO it returns, and the exceptions each one can throw.
---

Each request is a Saloon `Request`; send it with `$connector->send($request)` and
call `->dto()` on the response. Every constructor argument that identifies a user
is a [`SteamId`](/php-steam-api-sdk/guide#the-steamid-value-object). The shapes returned here are
documented in [Data objects](/php-steam-api-sdk/dto-reference).

## ResolveVanityUrlRequest

```text
new ResolveVanityUrlRequest(string $vanityName)
```

Resolves a vanity slug (the `<name>` in `steamcommunity.com/id/<name>`) to a
`SteamId`.

- Returns `SteamId`.
- Throws `SteamUserNotFoundException` when the slug does not resolve.

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;

$steamId = $connector->send(new ResolveVanityUrlRequest('gabelogannewell'))->dto();
```

## GetPlayerSummariesRequest

```text
new GetPlayerSummariesRequest(list<SteamId> $steamIds)
```

Fetches public profile summaries for a batch of players. Accepts **1 to 100** IDs.

- Returns `list<PlayerSummary>`.
- Throws `InvalidArgumentException` on an empty list.
- Throws `TooManySteamIdsException` when more than 100 IDs are passed.

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;

$summaries = $connector->send(new GetPlayerSummariesRequest([$steamId]))->dto();

foreach ($summaries as $summary) {
    echo $summary->personaName, ' — ', $summary->profileUrl, PHP_EOL;
}
```

## GetFriendListRequest

```text
new GetFriendListRequest(SteamId $steamId, ?FriendRelationship $relationship = null)
```

Returns the player's friends. Pass a `FriendRelationship` to filter; omit it for all
relationships.

- Returns `list<Friend>`.

```php
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;

$friends = $connector
    ->send(new GetFriendListRequest($steamId, FriendRelationship::Friend))
    ->dto();
```

## GetUserGroupListRequest

```text
new GetUserGroupListRequest(SteamId $steamId)
```

Lists the Steam groups a player belongs to. Only the group IDs come back — Steam
exposes no group metadata on this endpoint.

- Returns `list<UserGroup>`.
- Throws `ProfileNotPublicException` when the profile is hidden.

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;

$groups = $connector->send(new GetUserGroupListRequest($steamId))->dto();

foreach ($groups as $group) {
    echo $group->gid, PHP_EOL;
}
```

## GetPlayerBansRequest

```text
new GetPlayerBansRequest(list<SteamId> $steamIds)
```

Fetches VAC, game, community and economy ban status for a batch of players. Accepts
**1 to 100** IDs. Unlike the other batch endpoints this one needs no public profile —
ban status comes back for private profiles too.

- Returns `list<PlayerBan>`.
- Throws `InvalidArgumentException` on an empty list.
- Throws `TooManySteamIdsException` when more than 100 IDs are passed.

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;

$bans = $connector->send(new GetPlayerBansRequest([$steamId]))->dto();

foreach ($bans as $ban) {
    echo $ban->steamId, ' — VAC bans: ', $ban->numberOfVacBans, PHP_EOL;
}
```

## GetOwnedGamesRequest

```text
new GetOwnedGamesRequest(
    SteamId $steamId,
    list<int> $appIdsFilter = [],
    bool $includeAppInfo = false,
    bool $includePlayedFreeGames = false,
)
```

Lists the games a player owns. `appIdsFilter` narrows the result to specific app
IDs; `includeAppInfo` adds names and icons; `includePlayedFreeGames` includes free
games the player has launched.

- Returns `list<OwnedGame>`.
- Throws `ProfileNotPublicException` when the profile or its games list is hidden.

```php
use Fkrzski\SteamApiSdk\Http\Requests\IPlayerService\GetOwnedGamesRequest;

$library = $connector->send(new GetOwnedGamesRequest(
    steamId: $steamId,
    appIdsFilter: [381210],
    includeAppInfo: true,
))->dto();
```

## GetUserStatsForGameRequest

```text
new GetUserStatsForGameRequest(SteamId $steamId, int $appId, ?string $language = null)
```

Returns a player's stats and achievement flags for one game. `language` localises
achievement metadata (e.g. `'english'`).

- Returns `UserStats`.
- Throws `ProfileNotPublicException` when the profile is hidden.

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetUserStatsForGameRequest;

$stats = $connector
    ->send(new GetUserStatsForGameRequest(steamId: $steamId, appId: 381210))
    ->dto();

foreach ($stats->stats as $stat) {
    echo $stat->name, ' = ', $stat->value, PHP_EOL;
}
```

## GetPlayerAchievementsRequest

```text
new GetPlayerAchievementsRequest(SteamId $steamId, int $appId, ?string $language = null)
```

Returns a player's achievements for one game, each with its unlock state and time.
`language` localises the achievement name and description.

- Returns `PlayerAchievements`.
- Throws `StatsUnavailableException` when `appId` exposes no achievements **or** the profile is hidden — Steam reports both identically.

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetPlayerAchievementsRequest;

$result = $connector->send(new GetPlayerAchievementsRequest(
    steamId: $steamId,
    appId: 381210,
    language: 'english',
))->dto();

foreach ($result->achievements as $achievement) {
    echo $achievement->apiName, ' — ', $achievement->achieved ? 'unlocked' : 'locked', PHP_EOL;
}
```
