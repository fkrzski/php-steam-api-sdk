# Changelog

All notable changes to `php-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `GetPlayerBansRequest` (`ISteamUser`) with the `PlayerBan` DTO and `EconomyBan` enum. Steam's value set is open, so unknown values fall back to `Unknown` instead of throwing ([#18](https://github.com/fkrzski/php-steam-api-sdk/issues/18)).
- `GetUserGroupListRequest` (`ISteamUser`) with the `UserGroup` DTO ([#19](https://github.com/fkrzski/php-steam-api-sdk/issues/19)).
- `InvalidApiKeyException` for a missing (400) or rejected (403) API key. Steam reports both as HTML, which is what tells them apart from a private profile on the same status.
- `StatsUnavailableException`, thrown by `GetPlayerAchievementsRequest` when Steam withholds achievements. It names both causes, because the `Requested app has no stats` body is identical for an app without achievements and for a private profile.
- `SteamApiException::$response` exposing the response behind an HTTP failure.
- `JsonSerializable` on `SteamId`, which now encodes to a bare string instead of `{"value":"<id>"}` ([#25](https://github.com/fkrzski/php-steam-api-sdk/issues/25)).

### Changed

- **BC break.** `SteamApiException` declares its own constructor, `__construct(string $message, ?Response $response = null)`. `getCode()` is now the HTTP status, or `0` when the failure was raised before a request went out.
- **BC break.** HTTP failures raise SDK exceptions instead of Saloon's `RequestException` subclasses, so catching `SteamApiException` covers every failure ([#20](https://github.com/fkrzski/php-steam-api-sdk/issues/20)).
- **BC break.** `TooManySteamIdsException::forCount()` takes a required `string $endpoint`, so the message names the request that hit the cap.
- **BC break.** `CommentPermission` and `CommunityVisibility` are backed enums: `CommunityVisibility` by `int` using Steam's own `communityvisibilitystate` codes (`Hidden = 1`, `Visible = 3`), `CommentPermission` by `string` (`'everyone'`, `'nobody'`, `'friends_only'`), because Steam omits `commentpermission` entirely for the friends-only case and so has no wire integer for it. Case names and `fromApiValue()` are unchanged; only `UnitEnum` type hints and `instanceof UnitEnum` checks need updating.
- `GetPlayerAchievementsRequest`, `GetUserStatsForGameRequest` and `GetUserGroupListRequest` no longer detect a private profile from the response body. Steam answers with an error status in all three cases (400, 400 and 403), so none of those checks ever ran.

### Fixed

- `PlayerSummary` can be passed to `json_encode()`. Both enums it exposes were pure, so encoding it — or any structure containing it — returned `false` with `Non-backed enums have no default serialization` ([#25](https://github.com/fkrzski/php-steam-api-sdk/issues/25)). `DateTimeImmutable` properties on the DTOs still serialize as PHP's internal shape and remain open there.
- `SteamId::tryFromInput()` and `SteamId::extractVanityName()` parse profile and vanity URLs carrying a sub-path, query string or fragment (e.g. `/profiles/<id>/stats/`, `/id/<nick>?snr=…`). Previously `tryFromInput()` returned `null` and `extractVanityName()` returned an unusable slug.
- Rate limit counters are scoped to the API key instead of shared by every connector in the process, so one key can no longer spend another's daily budget. The key is hashed into the store key, never written in plaintext, and counters already held in a persistent store restart from zero once ([#30](https://github.com/fkrzski/php-steam-api-sdk/issues/30)).

## [0.3.0] - 2026-07-30

### Added

- `GetFriendListRequest` (`ISteamUser`) with the `Friend` DTO and `FriendRelationship` enum.
- Documentation site under `docs/`, published at [docs.fkrzski.dev/php-steam-api-sdk](https://docs.fkrzski.dev/php-steam-api-sdk), with a CI job validating the docs frontmatter.

### Changed

- **BC break.** Request classes are grouped into per-interface subnamespaces (`Http\Requests\ISteamUser`, `Http\Requests\ISteamUserStats`, `Http\Requests\IPlayerService`). Update imports when upgrading.
- Test fixtures are grouped into per-interface, per-request folders (`tests/Fixtures/Saloon/<Interface>/<Request>/`) with shortened variant filenames (`default`, `empty`, `private`, …), mirroring the request and test layout.
- GitHub Actions are pinned to version tags instead of commit hashes, for readability.
- The PHPUnit cache lives under `.cache/phpunit`, so PHPStan, Rector and PHPUnit share one `.cache` directory.

### Removed

- Dead `.gitignore` entries for unused tooling (`.php-cs-fixer.php`, `.php-cs-fixer.cache`, `.phpunit.result.cache`, `.phpunit.cache`).

## [0.2.0] - 2026-06-08

### Added

- Mutation testing via Pest (`composer test:mutate`), enforcing a 100% MSI threshold in CI.
- `laravel/pao` as a development dependency.

### Changed

- PHPStan and Rector are cached in the formats workflow, to speed up CI.
- Pull requests no longer trigger duplicate workflow runs.
- `.gitattributes` covers distribution archives and line-ending handling.
- The README carries build, coverage and version badges.
- Bumped `shivammathur/setup-php` (2.37.0 → 2.37.1) and `actions/checkout` (6.0.2 → 6.0.3).

## [0.1.0] - 2026-06-03

### Added

- `SteamConnector`, built on Saloon v4, with the daily 100 000-request rate limit baked in via `saloonphp/rate-limit-plugin` (`SteamRateLimitException` once exhausted).
- `SteamConfig` for the API key and an optional custom `RateLimitStore`.
- `SteamId` value object, with strict `fromSteamId64()`, lenient `tryFromInput()` and `extractVanityName()` helpers.
- Requests with readonly DTO responses: `ResolveVanityUrlRequest`, `GetPlayerSummariesRequest` (batch, ≤100 IDs), `GetOwnedGamesRequest`, `GetUserStatsForGameRequest` and `GetPlayerAchievementsRequest`.
- Domain exception hierarchy rooted at `SteamApiException` (`InvalidSteamIdException`, `SteamUserNotFoundException`, `ProfileNotPublicException`, `TooManySteamIdsException`, `SteamRateLimitException`).
- Enums: `PersonaState`, `CommunityVisibility`, `CommentPermission`.
- Test suite (Pest) with Saloon `MockClient` fixtures, PHPStan max, 100% type coverage, Pint and Rector.

[Unreleased]: https://github.com/fkrzski/php-steam-api-sdk/compare/0.3.0...HEAD
[0.3.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.3.0
[0.2.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.2.0
[0.1.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.1.0
