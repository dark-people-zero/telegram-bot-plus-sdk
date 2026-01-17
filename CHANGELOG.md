# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [3.2.2] - 2026-01-16
### Changed
- **ActorMeta**
  - Updated `fromUser()` to hydrate meta using `new self($user->toArray())` instead of constructing an empty instance.
  - Updated `fromSender()` to hydrate meta using an explicit array payload (`id`, `firstName`, etc.) instead of constructing an empty instance.
  - Improves consistency and ensures inherited `User` fields are populated deterministically.

- **RoomMeta**
  - Updated `fromChat()` to hydrate meta using new `self($chat->toArray())` instead of constructing an empty instance.
  - Ensures inherited `Chat` fields are populated from the source chat payload.
- **TargetMeta**
  - Updated `fromUser()` to hydrate meta from the first user payload via `new self($user[0]->toArray())` instead of constructing an empty instance.
  - Aligns target meta initialization with the SDK `User` object model.

### Notes
- These changes standardize meta factories to initialize instances from SDK object payloads, improving completeness of populated fields.

## [3.2.1] - 2026-01-16

### Fixed

- **TargetMeta (newChatMembers handling)**
  - Normalized `newChatMembers` input to always pass an array of `User` objects.
  - Replaced direct property access with `getNewChatMembers()->all()` to ensure consistent `User[]` payload for `TargetMeta::fromUser()`.

## [3.2.0] - 2026-01-16
### Changed

- `TargetMeta`
  - Refactored to extend/consume `Telegram\Bot\Objects\User` instead of defining all fields manually.
  - Added `status` (e.g. `member`, `administrator`, `null`).
  - Added `all` to keep the full user payload (including multi-target cases like `newChatMembers`).

- `RoomMeta`
  - Refactored to extend/consume `Telegram\Bot\Objects\Chat` instead of defining all fields manually.
  - Added `threadId`, resolved from `update->getMessage()->getMessageThreadId()`.

- `ActorMeta`
  - Refactored to extend/consume `Telegram\Bot\Objects\User` instead of defining all fields manually.
  - Added:
    - `type` (`user|bot|sender_chat|unknown`)
    - `role` (`creator|administrator|member|restricted|left|kicked|unknown`)
    - `title` (only for `sender_chat`)
    - `fromUser` (clone/copy from user)
    - `fromSender` (map sender_chat keys needed into user-like meta)

- `ChangeMeta`
  - `kind` is now optional (default: `unknown`).
  - `before` and `after` now use `Telegram\Bot\Objects\ChatMember` instead of plain arrays.

- `PermissionResolver`
  - Refactored to consume data from the SDK `Update` object instead of raw update arrays.

- `TelegramUpdateAnalyzer`
  - All analyzer methods now consume the SDK `Update` object (no longer reading raw update payload directly).

### Notes

- This release aligns meta/analyzer/resolver layers with irazasyed SDK object model for consistency and reduces manual parsing/normalization.
- If you previously depended on Meta internal arrays/properties, adapt to the new SDK-based objects and added helper constructors.

## [3.1.8] - 2026-01-16

### Added

- `TargetMeta`
  - Added `username` field.
  - Added `is_premium` field.
  - Target metadata now exposes additional user attributes for richer context and rule evaluation.

### Notes
- New fields are populated when available from Telegram update payload.
- Existing behavior remains unchanged for updates that do not provide these attributes.

## [3.1.7] - 2026-01-16

### Added

- `TargetMeta`
  - New metadata class to represent the _target_ of a Telegram update.
  - Encapsulates information about the affected user(s).
- `TelegramUpdateMeta`
  - Added `target` property containing an instance of `TargetMeta`.
- `TelegramUpdateAnalyzer`
  - Added `extractTarget()` method.
  - Analyzer now detects and populates target metadata during update analysis.

### Notes

- `target` represents _who is affected by the action_, not the actor.
- Target detection follows Telegram Bot API update structures (chat member updates, join/leave events, etc.).
- For updates without a meaningful target, `target` will be `null`.


## [3.1.6] - 2026-01-16

### Changed

- **TelegramUpdateAnalyzer**
  - Refactored update retrieval to rely directly on `TelegramContext` update object.
  - Removed fallback logic and best-effort access.
  - Update data is now obtained via:
  ```php
  $update = $ctx->update->all();
  ```
  - Improves consistency, readability, and enforces the finalized `TelegramContext` contract.

## [3.1.5] - 2026-01-16

### Fixed

- Prevented `Property [text] does not exist on this collection instance` by safely reading `message.text` when the message payload is an empty `Collection`.
- Improved robustness of message text extraction for non-text / non-message updates.

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
