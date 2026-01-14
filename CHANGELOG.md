# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

---

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
