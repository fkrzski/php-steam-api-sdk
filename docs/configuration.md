---
title: Configuration
description: Configure the SteamConnector through the readonly SteamConfig — the API key, the rate-limit store, and how the daily quota is enforced across processes.
---

The connector is configured once, through a readonly `SteamConfig`. Everything the
SDK needs at runtime lives on that object.

## The connector

`SteamConnector` holds your credentials and targets `https://api.steampowered.com`.
Build it with a `SteamConfig`:

```php
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;

$connector = new SteamConnector(new SteamConfig(apiKey: 'YOUR_STEAM_API_KEY'));
```

The API key is appended to every request as the `key` query parameter, so you never
pass it per call.

## SteamConfig options

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `apiKey` | `string` | — (required) | Your Steam Web API key. |
| `rateLimitStore` | `?RateLimitStore` | `MemoryStore` | Where the daily request budget is tracked. |

`SteamConfig` is a `final readonly` class — construct a new one to change settings
rather than mutating an existing instance.

## Rate limiting

The Steam Web API allows **100 000 requests per API key per day**. The connector
enforces this via [`saloonphp/rate-limit-plugin`](https://github.com/saloonphp/rate-limit-plugin)
and throws `SteamRateLimitException` once the budget is spent.

## Multiple API keys

The budget is tracked per API key, so a process — or a shared store — can serve several
keys without them draining each other's quota. Only a SHA-256 hash of the key ever
reaches the store.

## Sharing the budget across processes

The default `MemoryStore` only tracks usage within a single PHP process — fine for a
script, but each queue worker or FPM process would get its own counter. For
multi-process deployments, inject a shared store:

```php
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\RateLimitPlugin\Stores\PredisStore;

$connector = new SteamConnector(new SteamConfig(
    apiKey: 'YOUR_STEAM_API_KEY',
    rateLimitStore: new PredisStore($predis),
));
```

Any `Saloon\RateLimitPlugin\Contracts\RateLimitStore` is accepted — Predis, PSR-16,
file, Laravel cache, or your own implementation.
