# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [3.1.4] - 2026-01-16

### Fixed

- Safely accessed optional `message.text` using the nullsafe operator to prevent errors when the message payload is missing or not an object.
- Improved robustness when handling non-text Telegram updates (e.g. callbacks, service messages).

## [3.1.3] - 2026-01-16

### Fixed

- Updated `TelegramBot::handler()` invocation to pass the incoming request payload.
- Prevented runtime errors caused by calling `handler()` without the required argument.

## [3.1.2] - 2026-01-16

### Changed

- Refactored `HasPlusMiddlewareMeta` to be a method-only trait.
- Removed property definitions from `HasPlusMiddlewareMeta` and resolved meta values dynamically from class properties when available.
- This allows middleware classes to define their own meta properties (`$events`, `$forBot`, `$commands`) without property conflicts.

### Notes

- Middleware classes using `HasPlusMiddlewareMeta` must now define the corresponding properties themselves if they rely on property-based configuration.


## [3.1.1] - 2026-01-16

### Fixed
- Aligned `HasPlusMiddlewareMeta::forBot()` return type and property definition with the `PlusMiddlewareMeta` contract (`string|array|null`) to prevent type incompatibility during middleware composition.

## [3.1.0] - 2026-01-15

### Added
- Introduced `TelegramUpdateMeta` as a per-update analyzed metadata object (actor, room, action, before/after change, and permissions).
- Added `TelegramUpdateAnalyzer` to build `TelegramUpdateMeta` from the incoming update in a deterministic, fast way.
- Added permissions layer:
  - `PermissionCatalog` (permission keys + UI mapping + grouping)
  - `PermissionResolver` (resolves permissions by source and produces `PermissionBag`)
- Container bindings:
  - `TelegramBotServiceProvider` now registers `PermissionCatalog` and `PermissionResolver`.
  - `BotsManagerPlus` now creates `TelegramUpdateAnalyzer` and registers `TelegramUpdateMeta` per update.

### Notes
- `TelegramUpdateMeta` is designed to be consumed by middleware/commands/services via dependency injection (constructor injection recommended).
- No network calls are performed during analysis/resolution (fast and deterministic).


## [3.0.3] - 2026-01-15

### Changed
- Updated the sample configuration to improve clarity and provide more accurate examples.


## [3.0.1] - 2026-01-14

### Changed
- Replaced `config('telegram.bots')` with `Config::get('telegram.bots', [])`
  in `routes/telegram.php` for improved safety and explicit default handling.


## [3.0.0] - 2026-01-14

### ⚠️ Breaking Changes
- Changed `TelegramEvents` namespace to remove the extra `Src` segment:
  - **Before**: `DarkPeople\TelegramBot\Src\Contracts\TelegramEvents`
  - **After**:  `DarkPeople\TelegramBot\Contracts\TelegramEvents`

  Consumers must update imports (`use` statements) accordingly.

### Fixed
- Updated `MiddlewareCompiler` to reference the new `TelegramEvents` namespace.
- Fixed PSR-4 autoload compliance by aligning namespaces with directory structure.


## [2.0.1] - 2026-01-14

### Fixed
- Fixed an internal reference in `MiddlewareCompiler` that still used the legacy
  `Packages\DarkPeople\TelegramBot\Src\Contracts\TelegramEvents` namespace.
  This prevented the package from booting correctly after upgrading to v2.0.0.


## [2.0.0] - 2026-01-14

### ⚠️ Breaking Changes
- Changed the namespace of `TelegramEvents`:
  - **Before**: `Packages\DarkPeople\TelegramBot\Src\Contracts\TelegramEvents`
  - **After**:  `DarkPeople\TelegramBot\Src\Contracts\TelegramEvents`

  This change affects all consumers that reference the old namespace and
  requires updating `use` statements accordingly.

### Changed
- Normalized internal namespaces to match the published package namespace.
- Improved consistency between PSR-4 autoloading and actual class locations.

---

## [1.0.0] - 2026-01-13

### Added
- Initial stable release of Telegram Bot Plus SDK.
- Multi-bot support with strict bot resolution.
- Middleware authoring and compilation system.
- Command scanning, grouping, and auto-registration (opt-in).
- Global and bot-specific middleware pipelines.
- Laravel Service Provider auto-registration.
- Full configuration-driven setup with runtime-compiled outputs.

---

## Unreleased
- Future improvements and enhancements will be listed here.
