# Changelog

All notable changes to `php-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.1.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/v0.1.0