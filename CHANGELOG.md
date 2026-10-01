# Changelog

All notable changes to `php-steam-api-sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.7.0] - 2026-10-01

### Added

- `ApiKeyNotConfiguredException` for a request that needs an API key sent from a config carrying none, and for a blank key handed to `SteamConfig`. Both are raised locally, so neither costs a round trip or a slot of the daily budget ([#59](https://github.com/fkrzski/php-steam-api-sdk/issues/59)).
- `GetSdrConfigRequest` (`ISteamApps`) with the `SdrConfig`, `SdrPointOfPresence` and `SdrRelay` DTOs, mapping the Steam Datagram Relay network a game connects through, keyed by point-of-presence code and reached anonymously as `$connector->apps()->sdrConfig()`. An app ID Steam does not know answers `500`, which raises `AppNotFoundException` at once rather than being retried ([#66](https://github.com/fkrzski/php-steam-api-sdk/issues/66)).
- `GetServersAtAddressRequest` (`ISteamApps`) with the `GameServer` DTO and the `ServerRegion` enum, listing the game servers Steam knows at an IPv4 address, reached anonymously as `$connector->apps()->serversAtAddress()`; an address with none returns an empty list. An address Steam rejects raises the new `InvalidServerAddressException`, and any other refusal raises the root `SteamApiException` carrying Steam's own message ([#65](https://github.com/fkrzski/php-steam-api-sdk/issues/65)).
- `SteamConnectionException` for a request that never reached Steam — a timeout, a DNS failure, a TLS error, a refused connection. It carries no response and a `0` code, with Saloon's `FatalRequestException` on `getPrevious()` ([#60](https://github.com/fkrzski/php-steam-api-sdk/issues/60)).
- `UpToDateCheckRequest` (`ISteamApps`) with the `AppVersionCheck` DTO, telling whether a game server's version is current and which one Steam requires, reached anonymously through the new `AppsResource` as `$connector->apps()->upToDateCheck()`. Steam answers an app ID it does not know exactly like a game running no versioned servers, so both raise the new `AppVersionUnavailableException` ([#64](https://github.com/fkrzski/php-steam-api-sdk/issues/64)).
- `connectTimeout` and `requestTimeout` on `SteamConfig`, in seconds, falling back to Saloon's 10 and 30 when left `null`. `0` disables the limit and a negative value throws `InvalidTimeoutException` ([#61](https://github.com/fkrzski/php-steam-api-sdk/issues/61)).
- `tries`, `retryInterval` (in milliseconds) and `exponentialBackoff` on `SteamConfig`, defaulting to a single attempt; fewer than one try or a negative interval throws `InvalidRetryException`. Only a request Steam never answered or a `5xx` is retried, never a `4xx` or `SteamRateLimitException`, because every attempt spends a slot of the daily budget ([#62](https://github.com/fkrzski/php-steam-api-sdk/issues/62)).

### Changed

- **BC break.** `SteamConfig::$apiKey` is a `?string` defaulting to `null`, which makes `GetNumberOfCurrentPlayersRequest` and `GetGlobalAchievementPercentagesForAppRequest` reachable with no key configured. Callers reading the property as a `string` have to handle `null` ([#59](https://github.com/fkrzski/php-steam-api-sdk/issues/59)).
- **BC break.** Transport failures out of `send()`, `sendAsync()` and `pool()` surface as `SteamConnectionException` rather than Saloon's `FatalRequestException`. Callers catching the Saloon exception have to catch the SDK one instead ([#60](https://github.com/fkrzski/php-steam-api-sdk/issues/60)).
- A connector without an API key meters no daily budget, because Steam bills the 100 000 requests to the key; a `429` from Steam still raises `SteamRateLimitException` ([#59](https://github.com/fkrzski/php-steam-api-sdk/issues/59)).
- `InvalidApiKeyException::missing()` reads "Steam received no API key" rather than "Steam API key is missing", so Steam's verdict no longer reads like the local configuration error ([#59](https://github.com/fkrzski/php-steam-api-sdk/issues/59)).
- A status the SDK gives no meaning of its own raises the root `SteamApiException` with Saloon's `RequestException` on `getPrevious()`, which is what lets the retry loop see it ([#62](https://github.com/fkrzski/php-steam-api-sdk/issues/62)).

## [0.6.0] - 2026-09-01

### Added

- `AppNotFoundException` for an app ID Steam does not know, thrown only where the response separates it from an app that merely exposes no stats. The `400` on `GetPlayerAchievementsRequest` does not, so it keeps `StatsUnavailableException` ([#47](https://github.com/fkrzski/php-steam-api-sdk/issues/47)).
- `GetGlobalAchievementPercentagesForAppRequest` (`ISteamUserStats`) with the `GlobalAchievement` DTO, listing how much of the playerbase has unlocked each achievement in a game. Steam spells the parameter `gameid` rather than `appid` and the SDK follows, so this one method takes `$gameId`; `403` comes back identically for a game carrying no achievements and for a game ID Steam does not know, so both raise `StatsUnavailableException` ([#50](https://github.com/fkrzski/php-steam-api-sdk/issues/50)).
- `GetNumberOfCurrentPlayersRequest` (`ISteamUserStats`) returning a game's concurrent player count as a plain `int`. Steam serves the endpoint anonymously, so requests implementing the new `SendsNoApiKey` contract go out with the configured key stripped from the query ([#49](https://github.com/fkrzski/php-steam-api-sdk/issues/49)).
- `GetSchemaForGameRequest` (`ISteamUserStats`) with the `GameSchema`, `SchemaAchievement` and `SchemaStat` DTOs, listing every stat and achievement a game publishes, localised through the `Language` enum. An app publishing no schema comes back as an empty `GameSchema` rather than a failure, because Steam answers `400` for an app ID it does not know and that separates the two ([#48](https://github.com/fkrzski/php-steam-api-sdk/issues/48)).
- `Language` enum backing Steam's `l` query parameter, and a `language` on `SteamConfig` that applies it to every request whose endpoint localises its payload. Steam's codes are Valve's own rather than ISO, so the enum transcribes their table instead of mapping to one ([#46](https://github.com/fkrzski/php-steam-api-sdk/issues/46)).

### Changed

- **BC break.** `$language` is a `?Language` instead of a `?string` on `GetPlayerAchievementsRequest`, `GetUserStatsForGameRequest` and both `StatsResource` methods. Callers passing a raw code swap `'english'` for `Language::English` ([#46](https://github.com/fkrzski/php-steam-api-sdk/issues/46)).

## [0.5.0] - 2026-08-26

### Added

- Fluent resources on the connector: `PlayersResource`, `UsersResource` and `StatsResource`, reached through `$connector->players()`, `->users()` and `->stats()`. Each method sends its request and returns the DTO; the request classes are unchanged and stay the way to reach the `Response` itself or Saloon's `pool()` ([#40](https://github.com/fkrzski/php-steam-api-sdk/issues/40)).
- `GetBadgesRequest` (`IPlayerService`) with the `PlayerBadges` and `Badge` DTOs, covering every earned badge alongside the experience behind the community level. An account with no badges answers exactly like a SteamID64 that belongs to nobody, so neither is treated as a failure ([#38](https://github.com/fkrzski/php-steam-api-sdk/issues/38)).
- `GetCommunityBadgeProgressRequest` (`IPlayerService`) with the `CommunityBadgeQuest` DTO, listing the quests behind the Steam community badge and whether each one is done. Steam's `badgeid` filter is not exposed, because every ID other than `0` answers with the same empty payload a withheld profile gets ([#39](https://github.com/fkrzski/php-steam-api-sdk/issues/39)).
- `GetRecentlyPlayedGamesRequest` (`IPlayerService`) with the `RecentlyPlayedGames` and `RecentlyPlayedGame` DTOs and an optional `count` limit. `RecentlyPlayedGames::$totalCount` carries Steam's unlimited total, so a list truncated by `count` stays distinguishable from a complete one ([#36](https://github.com/fkrzski/php-steam-api-sdk/issues/36)).
- `GetSteamLevelRequest` (`IPlayerService`) returning the player's Steam community level as a plain `int`. Level `0` is also what Steam answers for a SteamID64 that belongs to no account, so it is not treated as a failure ([#37](https://github.com/fkrzski/php-steam-api-sdk/issues/37)).
- Testing guidance in the docs: faking the connector with Saloon's `MockClient`, and clearing the `MemoryStore` daily counter between tests.

### Changed

- Documentation pages are `.mdx` and use Starlight components — tabs for alternatives, asides for caveats, steps for setup, badges for what each request throws, and filename bars on code blocks.

### Fixed

- `InvalidApiKeyException` also covers a `401` whose HTML names the `key` parameter, not just a `403`. `GetCommunityBadgeProgressRequest` answers `401` for a broken or absent key, which used to surface as `ProfileNotPublicException`.
- **BC break.** `PlayerSummary::$timeCreated` is nullable, because Steam omits `timecreated` for a hidden profile and reading it unguarded fataled the whole `GetPlayerSummariesRequest` batch. Callers reading the date need a null check ([#33](https://github.com/fkrzski/php-steam-api-sdk/issues/33)).

## [0.4.0] - 2026-08-16

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

[Unreleased]: https://github.com/fkrzski/php-steam-api-sdk/compare/0.7.0...HEAD
[0.7.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.7.0
[0.6.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.6.0
[0.5.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.5.0
[0.4.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.4.0
[0.3.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.3.0
[0.2.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.2.0
[0.1.0]: https://github.com/fkrzski/php-steam-api-sdk/releases/tag/0.1.0
