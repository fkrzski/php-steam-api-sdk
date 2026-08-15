---
title: Data objects
description: The readonly DTOs and enums the SDK decodes Steam responses into — every property with its type, plus the enum cases behind player state and visibility.
---

Every request returns readonly DTOs — immutable objects with typed, public
properties. Nullable properties (`?`) map to fields Steam omits for private or
incomplete profiles. Timestamps are always `DateTimeImmutable`.

## PlayerSummary

Returned by [`GetPlayerSummariesRequest`](/php-steam-api-sdk/api-reference#getplayersummariesrequest).

| Property | Type | Notes |
| --- | --- | --- |
| `steamId` | `SteamId` | The player's 64-bit ID. |
| `personaName` | `string` | Current display name. |
| `profileUrl` | `string` | Public profile URL. |
| `avatarUrl` | `string` | 32×32 avatar. |
| `avatarMediumUrl` | `string` | 64×64 avatar. |
| `avatarFullUrl` | `string` | 184×184 avatar. |
| `avatarHash` | `string` | Avatar content hash. |
| `communityVisibility` | `CommunityVisibility` | Whether the profile is visible to you. |
| `hasCommunityProfile` | `bool` | Player has set up a community profile. |
| `commentPermission` | `CommentPermission` | Who may comment on the profile. |
| `personaState` | `PersonaState` | Online / away / offline, etc. |
| `realName` | `?string` | Real name, if shared. |
| `primaryClanId` | `?string` | Primary group ID, if any. |
| `timeCreated` | `DateTimeImmutable` | Account creation time. |
| `lastLogOff` | `?DateTimeImmutable` | Last logout, if exposed. |
| `gameId` | `?string` | App ID currently being played. |
| `gameExtraInfo` | `?string` | Title of the game being played. |
| `gameServerIp` | `?string` | Game server address, if in-game. |
| `countryCode` | `?string` | ISO country code, if shared. |
| `stateCode` | `?string` | State/region code, if shared. |
| `cityId` | `?int` | Steam city ID, if shared. |

## Friend

Returned by [`GetFriendListRequest`](/php-steam-api-sdk/api-reference#getfriendlistrequest).

| Property | Type | Notes |
| --- | --- | --- |
| `steamId` | `SteamId` | The friend's 64-bit ID. |
| `relationship` | `FriendRelationship` | Relationship to the queried user. |
| `friendSince` | `DateTimeImmutable` | When the friendship was formed. |

## OwnedGame

Returned by [`GetOwnedGamesRequest`](/php-steam-api-sdk/api-reference#getownedgamesrequest).

| Property | Type | Notes |
| --- | --- | --- |
| `appId` | `int` | Steam app ID. |
| `playtimeForever` | `int` | Total playtime, in minutes. |
| `playtimeTwoWeeks` | `?int` | Playtime in the last two weeks, in minutes. |
| `name` | `?string` | Game title (only with `includeAppInfo`). |
| `imgIconUrl` | `?string` | Icon hash (only with `includeAppInfo`). |
| `hasCommunityVisibleStats` | `bool` | Game exposes community stats. |

## UserStats

Returned by [`GetUserStatsForGameRequest`](/php-steam-api-sdk/api-reference#getuserstatsforgamerequest).

| Property | Type | Notes |
| --- | --- | --- |
| `steamId` | `SteamId` | The player's 64-bit ID. |
| `gameName` | `string` | Game title. |
| `stats` | `list<UserStat>` | Numeric stats. |
| `achievements` | `list<UserStatAchievement>` | Achievement flags. |

`UserStat` has `name` (`string`) and `value` (`int|float`). `UserStatAchievement`
has `name` (`string`) and `achieved` (`bool`).

## PlayerAchievements

Returned by [`GetPlayerAchievementsRequest`](/php-steam-api-sdk/api-reference#getplayerachievementsrequest).

| Property | Type | Notes |
| --- | --- | --- |
| `steamId` | `SteamId` | The player's 64-bit ID. |
| `gameName` | `string` | Game title. |
| `achievements` | `list<PlayerAchievement>` | Per-achievement state. |

Each `PlayerAchievement` carries:

| Property | Type | Notes |
| --- | --- | --- |
| `apiName` | `string` | Stable API identifier. |
| `achieved` | `bool` | Whether it is unlocked. |
| `unlockedAt` | `?DateTimeImmutable` | Unlock time, `null` if locked. |
| `name` | `?string` | Display name (needs `language`). |
| `description` | `?string` | Description (needs `language`). |

## Enums

`PersonaState` (backed by `int`):

| Case | Value |
| --- | --- |
| `Offline` | `0` |
| `Online` | `1` |
| `Busy` | `2` |
| `Away` | `3` |
| `Snooze` | `4` |
| `LookingToTrade` | `5` |
| `LookingToPlay` | `6` |

`CommunityVisibility` (backed by `int`) — `Hidden` (`1`), `Visible` (`3`). The backing
values are Steam's own `communityvisibilitystate` codes.

`CommentPermission` (backed by `string`) — `Everyone` (`'everyone'`), `Nobody`
(`'nobody'`), `FriendsOnly` (`'friends_only'`). Steam sends `commentpermission` as an
integer and omits the key entirely for the friends-only case, so these backing values
are the SDK's own vocabulary rather than the wire values. Use `fromApiValue()` to map
from the raw API payload.

`FriendRelationship` (backed by `string`) — `All` (`'all'`), `Friend` (`'friend'`).
