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

## Sending requests

Every request is a plain Saloon `Request`. Send it through the connector and call
`->dto()` to decode the response into typed objects:

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;

$summaries = $connector
    ->send(new GetPlayerSummariesRequest([$id]))
    ->dto();
```

The connector uses Saloon's `AlwaysThrowOnErrors`, so a non-2xx response raises a
Saloon exception before you ever reach `->dto()`. Domain-level problems (a private
profile, an unknown vanity URL) surface as SDK exceptions instead — see below. Which
request returns which DTO is listed in the [API reference](/php-steam-api-sdk/api-reference).

## Exceptions

Every SDK failure extends `SteamApiException`, so one catch handles them all:

```text
SteamApiException                (root, extends RuntimeException)
├── InvalidSteamIdException      Malformed SteamID64.
├── SteamUserNotFoundException   Vanity URL unresolved or profile missing.
├── ProfileNotPublicException    Profile, games list, or stats are private.
├── TooManySteamIdsException     More than 100 IDs in a batch request.
└── SteamRateLimitException      Daily 100k quota reached; exposes the offending Limit.
```

Catch the leaf you care about, or the root `SteamApiException` to handle every
SDK failure uniformly. The [API reference](/php-steam-api-sdk/api-reference) notes which request throws
which exception.
