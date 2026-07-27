# PHP Steam API SDK

![Banner of PHP Steam API SDK](art/banner.png)

[![License](https://img.shields.io/packagist/l/fkrzski/php-steam-api-sdk.svg?style=for-the-badge)](https://packagist.org/packages/fkrzski/php-steam-api-sdk)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fkrzski/php-steam-api-sdk.svg?style=for-the-badge)](https://packagist.org/packages/fkrzski/php-steam-api-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/fkrzski/php-steam-api-sdk.svg?style=for-the-badge)](https://packagist.org/packages/fkrzski/php-steam-api-sdk)
[![Tests](https://img.shields.io/github/actions/workflow/status/fkrzski/php-steam-api-sdk/tests.yml?branch=master&label=tests&style=for-the-badge)](https://github.com/fkrzski/php-steam-api-sdk/actions/workflows/tests.yml)

Framework-agnostic PHP SDK for the [Steam Web API](https://steamcommunity.com/dev), built on top of [Saloon](https://docs.saloon.dev/) v4.

- Strong types (PHP 8.5, PHPStan max, 100% type coverage).
- Readonly DTOs with `DateTimeImmutable` instead of framework date objects.
- Domain exception hierarchy rooted at `SteamApiException`.
- Daily 100 000-request rate limit baked in via [`saloonphp/rate-limit-plugin`](https://github.com/saloonphp/rate-limit-plugin).
- Zero framework coupling — a [Laravel bridge package](https://docs.fkrzski.dev/laravel-steam-api-sdk) ships separately.

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

## Documentation

Full documentation — every request, the `SteamId` value object, configuration, DTOs, and the exception hierarchy — lives at **[docs.fkrzski.dev/php-steam-api-sdk](https://docs.fkrzski.dev/php-steam-api-sdk)**.

## License

MIT. See [LICENSE.md](LICENSE.md).
