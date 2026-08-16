---
title: Guide
description: The core building blocks — the SteamId value object, how requests turn into readonly DTOs, and the exception hierarchy every SDK failure shares.
---

Once you have a [configured connector](/php-steam-api-sdk/configuration), three concepts carry the
rest: the **`SteamId`** value object every request accepts, the **requests** you
send, and the readonly **DTOs** you get back.

## The `SteamId` value object

`SteamId` is the only accepted identifier across the SDK — no raw strings. Build one
from a verified 64-bit ID, or parse untrusted user input:

```php
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

// Strict — throws InvalidSteamIdException on anything but a 17-digit numeric ID.
$id = SteamId::fromSteamId64('76561198000000000');

// Lenient — returns null for anything that is not a SteamID64 or /profiles/<id> URL.
$id = SteamId::tryFromInput('https://steamcommunity.com/profiles/76561198000000000');

// Pull the vanity slug out of a /id/<name> URL to resolve it (see ResolveVanityUrlRequest).
$vanity = SteamId::extractVanityName('https://steamcommunity.com/id/gabelogannewell/');
```

The underlying value is available as `$id->value` (or `(string) $id`), and two IDs
compare with `$id->equals($other)`.

`SteamId` implements `JsonSerializable`, so it encodes as a bare string —
`json_encode(['steam_id' => $id])` yields `{"steam_id":"76561198000000000"}`, not a
nested object. Decoding is not symmetric: rebuild the value object with
`SteamId::fromSteamId64()` on the way back.

## Sending requests

Every request is a plain Saloon `Request`. Send it through the connector and call
`->dto()` to decode the response into typed objects:

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;

$summaries = $connector
    ->send(new GetPlayerSummariesRequest([$id]))
    ->dto();
```

The connector uses Saloon's `AlwaysThrowOnErrors`, so a non-2xx response fails before
you ever reach `->dto()`. Those failures are translated into the SDK's own exceptions,
so no Saloon class ever escapes — see below. Which request returns which DTO is listed
in the [API reference](/php-steam-api-sdk/api-reference).

## Exceptions

Every SDK failure extends `SteamApiException`, so one catch handles them all:

```text
SteamApiException                (root, extends RuntimeException)
├── InvalidSteamIdException      Malformed SteamID64.
├── SteamUserNotFoundException   Vanity URL unresolved or profile missing.
├── ProfileNotPublicException    Profile, games list, groups, or stats are private.
├── InvalidApiKeyException       API key missing, wrong, or revoked.
├── StatsUnavailableException    Stats withheld: no such stats, or private profile.
├── TooManySteamIdsException     More than 100 IDs in a batch request.
└── SteamRateLimitException      Daily 100k quota reached; exposes the offending Limit.
```

Catch the leaf you care about, or the root `SteamApiException` to handle every
SDK failure uniformly. The [API reference](/php-steam-api-sdk/api-reference) notes which request throws
which exception.

### Inspecting the failed response

Exceptions raised from an HTTP failure carry the response that produced them, and
their code is the HTTP status:

```php
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;

try {
    $summaries = $connector->send(new GetPlayerSummariesRequest([$id]))->dto();
} catch (SteamApiException $e) {
    $e->getCode();          // 403
    $e->response?->body();  // raw Steam payload, or null for client-side failures
}
```

`response` is `null` for failures raised before a request went out — a malformed
SteamID64, an oversized batch, or the daily quota being exhausted locally.

### How statuses are mapped

Steam signals failure in two different ways, and the SDK reads both:

| Steam's answer | Exception |
| --- | --- |
| `400` + HTML naming the `key` parameter | `InvalidApiKeyException` (missing) |
| `403` + HTML naming the `key` parameter | `InvalidApiKeyException` (rejected) |
| `400` + JSON, on `ISteamUserStats` | `StatsUnavailableException` / `ProfileNotPublicException` |
| `401` or `403` | `ProfileNotPublicException` |
| `429` | `SteamRateLimitException` |
| any other `4xx` / `5xx` | `SteamApiException` |
| `200` with an empty or `success: 42` payload | `ProfileNotPublicException` / `SteamUserNotFoundException` |

Steam overloads `400` across unrelated causes. Key errors are the one case it answers
with HTML rather than JSON, which is what keeps a misconfigured key from being reported
as a data problem. `GetPlayerAchievements` goes further and returns a byte-identical
`Requested app has no stats` body whether the app has no achievements or the profile is
private, so `StatsUnavailableException` names both causes rather than guessing between
them. `GetUserStatsForGame` answers an empty object for a private profile, which is
unambiguous and maps to `ProfileNotPublicException`.
