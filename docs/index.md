---
title: php-steam-api-sdk
description: Framework-agnostic PHP SDK for the Steam Web API, built on Saloon — typed readonly DTOs, a baked-in daily rate limit, and a clean exception hierarchy.
repository: https://github.com/fkrzski/php-steam-api-sdk
packagist: fkrzski/php-steam-api-sdk
status: stable
---

A framework-agnostic PHP SDK for the [Steam Web API](https://steamcommunity.com/dev),
built on top of [Saloon](https://docs.saloon.dev/) v4. Every request returns a
strongly-typed, readonly DTO — you never touch raw JSON, and a malformed profile
fails with a domain exception instead of a silent `null`.

## Why php-steam-api-sdk

- **Strongly typed** — PHP 8.5, PHPStan max, 100% type coverage.
- **Readonly DTOs** — immutable objects with `DateTimeImmutable`, never framework date wrappers.
- **Domain exceptions** — one hierarchy rooted at `SteamApiException` for every failure.
- **Rate limit baked in** — the 100 000 requests/day quota is enforced by the connector.
- **Zero framework coupling** — plain Saloon; a Laravel bridge ships separately.

## Requirements

- PHP **8.5+**
- Saloon **4+**

## Installation

```bash
composer require fkrzski/php-steam-api-sdk
```

## Quickstart

```php
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;

$connector = new SteamConnector(new SteamConfig(apiKey: 'YOUR_STEAM_API_KEY'));

$summaries = $connector
    ->send(new GetPlayerSummariesRequest([SteamId::fromSteamId64('76561198000000000')]))
    ->dto();

echo $summaries[0]->personaName;
```

Call `->dto()` on any Saloon response to get the readonly DTO for that request.

## Next steps

- [Guide](./php-steam-api-sdk/guide) — the `SteamId` value object, sending requests, and the exception hierarchy.
- [Configuration](./php-steam-api-sdk/configuration) — the connector, `SteamConfig` options, and rate limiting.
- [API reference](./php-steam-api-sdk/api-reference) — every request, its parameters, return type, and errors.
- [Data objects](./php-steam-api-sdk/dto-reference) — the DTOs and enums the SDK decodes responses into.
