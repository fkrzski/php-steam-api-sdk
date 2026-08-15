# Changelog

All notable changes to `php-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **BC break.** `TooManySteamIdsException::forCount()` now takes a required `string $endpoint`, so the message names the request that hit the cap.
- **BC break.** `CommentPermission` and `CommunityVisibility` are now backed enums. `CommunityVisibility` is backed by `int` using Steam's own `communityvisibilitystate` codes (`Hidden = 1`, `Visible = 3`); `CommentPermission` is backed by `string` (`'everyone'`, `'nobody'`, `'friends_only'`), because Steam omits the `commentpermission` key entirely for the friends-only case and so has no wire integer for it. Case names are unchanged and `fromApiValue()` keeps its signature and its `UnexpectedValueException` on unknown input — only code that relies on these being pure enums (a `UnitEnum` type hint, or `instanceof UnitEnum` checks) needs updating.

### Added

- `GetPlayerBans` endpoint (`ISteamUser`) with the `PlayerBan` DTO and `EconomyBan` enum. Steam's `EconomyBan` value set is open, so unrecognised values fall back to `Unknown` instead of throwing. Closes [#18](https://github.com/fkrzski/php-steam-api-sdk/issues/18).
- `GetUserGroupList` endpoint (`ISteamUser`) with the `UserGroup` DTO. Closes [#19](https://github.com/fkrzski/php-steam-api-sdk/issues/19).
- `SteamId` now implements `JsonSerializable` and encodes to a bare string. Previously `json_encode()` emitted `{"value":"<id>"}`, since the promoted `value` property is public. Part of [#25](https://github.com/fkrzski/php-steam-api-sdk/issues/25); `DateTimeImmutable` properties on the DTOs still serialize as PHP's internal shape and remain open there.

### Fixed

- `PlayerSummary` can now be passed to `json_encode()`. Both enums it exposes were pure, and PHP has no default serialization for those, so encoding a `PlayerSummary` — or any structure containing one — returned `false` with `Non-backed enums have no default serialization`. See [#25](https://github.com/fkrzski/php-steam-api-sdk/issues/25); `DateTimeImmutable` properties still serialize as PHP's internal shape and remain open there.
- `SteamId::tryFromInput()` and `SteamId::extractVanityName()` now parse profile and vanity URLs that carry a sub-path, query string or fragment (e.g. `/profiles/<id>/stats/`, `/id/<nick>?snr=…`). Previously the trailing segment made `tryFromInput()` return `null`, while `extractVanityName()` silently returned an unusable slug.

## [0.3.0] - 2026-07-30

### Added

- `GetFriendList` endpoint (`ISteamUser`) with the `Friend` DTO and `FriendRelationship` enum.
- Documentation site under `docs/`, published at [docs.fkrzski.dev/php-steam-api-sdk](https://docs.fkrzski.dev/php-steam-api-sdk), with a CI job validating the docs frontmatter.

### Changed

- Reorganize request classes into per-interface subnamespaces (`Http\Requests\ISteamUser`, `Http\Requests\ISteamUserStats`, `Http\Requests\IPlayerService`). Update imports when upgrading.
- Reorganize test fixtures into per-interface, per-request folders (`tests/Fixtures/Saloon/<Interface>/<Request>/`) with shortened variant filenames (`default`, `empty`, `private`, …), mirroring the request and test layout.
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

[0.3.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.3.0
[0.2.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.2.0
[0.1.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.1.0
