# CONTRIBUTING

Contributions are welcome, and are accepted via pull requests.
Please review these guidelines before submitting any pull requests.

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit and push
4. Open a pull request detailing your changes. Please follow the [template](.github/PULL_REQUEST_TEMPLATE.md).

## Guidelines

* Please ensure the coding style running `composer lint`.
* Send a coherent commit history, making sure each individual commit in your pull request is meaningful.
* You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
* Please remember that we follow [SemVer](http://semver.org/).
* Please record any user-facing change under `## [Unreleased]` in `CHANGELOG.md`, following the rules below.

## Backward compatibility

What SemVer covers is listed under [Versioning](https://docs.fkrzski.dev/php-steam-api-sdk#versioning) in the docs; a change to that surface updates the list too.

* **New classes are `final`.** `SteamApiException` is the one class meant to be extended, and the `@internal` `SteamApiRequest` the base every SDK request extends.
* **Mark what the SDK builds `@internal`:** a new DTO's constructor and `fromArray()`, a hook payload's constructor and an exception's named constructors. `InternalApiTest` fails without it.
* **A minor release** may add an endpoint, a DTO property, an exception leaf under the parent it narrows, a `SteamConfig` argument appended with a default, or raise the minimum PHP.
* **Tag what a minor adds `@since`** with its version, as `@since 1.1.0`: a class, method, property or `SteamConfig` argument. Anything without the tag dates from 1.0.0.
* **A major release** is needed to remove or rename anything covered, add a required argument, raise another exception for a documented Steam answer, or move to a new Saloon or rate limit plugin major. Its changelog entry starts with `**BC break.**`.
* **Deprecate in a minor first.** What a major removes or renames is marked `@deprecated` in an earlier minor, naming its replacement, and listed under `Deprecated` in the changelog.

## Changelog

`CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
New entries go under `## [Unreleased]`; keep them uniform:

* **Fixed section order:** Added, Changed, Deprecated, Removed, Fixed, Security. Omit the ones with no entries.
* **Start with the subject, not a verb.** Describe the state after the change — "Request classes are grouped into per-interface subnamespaces", not "Reorganize request classes".
* **One sentence for the change, at most one more for the reason or the consequence.** Anything longer belongs in the docs.
* **Name classes exactly as they exist in `src/`,** in backticks — `GetPlayerBansRequest`, not `GetPlayerBans`.
* **Prefix breaking changes with `**BC break.**`** and say what callers must update.
* **Put the issue link last, in parentheses:** `([#20](https://github.com/fkrzski/php-steam-api-sdk/issues/20))`.
* **No prose paragraph under a version heading** — the sections carry everything.
* **Collapse dependency bumps into a single line** rather than one per package.

## Setup

Clone your fork, then install the dev dependencies:
```bash
composer install
```
## Lint

Lint your code:
```bash
composer lint
```
## Tests

Run all tests:
```bash
composer test
```

Check types:
```bash
composer test:types
```

Unit tests:
```bash
composer test:unit
```
