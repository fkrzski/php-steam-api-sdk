# Changelog

All notable changes to `php-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `GetFriendList` endpoint (`ISteamUser`) with the `Friend` DTO and `FriendRelationship` enum.

### Changed

- Reorganize request classes into per-interface subnamespaces (`Http\Requests\ISteamUser`, `Http\Requests\ISteamUserStats`, `Http\Requests\IPlayerService`). Update imports when upgrading.
- Pin GitHub Actions to version tags instead of commit hashes for readability.
- Store the PHPUnit cache under `.cache/phpunit` so all tools (PHPStan, Rector, PHPUnit) share the `.cache` directory.

### Removed

- Drop dead `.gitignore` entries for unused tooling (`.php-cs-fixer.php`, `.php-cs-fixer.cache`, `.phpunit.result.cache`, `.phpunit.cache`).

## [0.2.0] - 2026-06-08

Maintenance release focused on tooling, testing and CI. No user-facing API changes.

### Added

- Mutation testing with Infection, enforcing a 100% MSI (Mutation Score Indicator) threshold.
- `laravel/pao` as a development dependency.

### Changed

- Cache PHPStan and Rector tools in the formats workflow to speed up CI.
- Prevent duplicate workflow runs triggered by pull requests.
- Improve `.gitattributes` for distribution archives and line-ending handling.
- Bump `shivammathur/setup-php` from 2.37.0 to 2.37.1.
- Bump `actions/checkout` from 6.0.2 to 6.0.3.
- Add badges to the README.

## [0.1.0] - 2026-06-03

Initial release.

### Added

- `SteamConnector` built on Saloon v4, with the daily 100 000-request rate limit baked in via `saloonphp/rate-limit-plugin` (`SteamRateLimitException` once exhausted).
- `SteamConfig` for the API key and an optional custom `RateLimitStore`.
- `SteamId` value object — strict `fromSteamId64()`, lenient `tryFromInput()`, and `extractVanityName()` helpers.
- Requests with readonly DTO responses:
  - `ResolveVanityUrlRequest`
  - `GetPlayerSummariesRequest` (batch, ≤100 IDs)
  - `GetOwnedGamesRequest`
  - `GetUserStatsForGameRequest`
  - `GetPlayerAchievementsRequest`
- Domain exception hierarchy rooted at `SteamApiException` (`InvalidSteamIdException`, `SteamUserNotFoundException`, `ProfileNotPublicException`, `TooManySteamIdsException`, `SteamRateLimitException`).
- Enums: `PersonaState`, `CommunityVisibility`, `CommentPermission`.
- Test suite (Pest) with Saloon `MockClient` fixtures, PHPStan max, 100% type coverage, Pint and Rector.

[0.2.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.2.0
[0.1.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.1.0
