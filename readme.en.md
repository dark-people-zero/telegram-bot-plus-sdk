
# Telegram Bot Plus SDK (Laravel)

Telegram Bot Plus SDK is an extension of [`irazasyed/telegram-bot-sdk`](https://github.com/irazasyed/telegram-bot-sdk)

that adds **auto command scanning**, **Telegram middleware**, and a **structured runtime pipeline** for Laravel applications.

Designed for:
- multi-bot setups
- scalability
- deterministic boot & runtime
- minimal manual configuration

---

# Listen Reply (Interactive Reply Listener)

Listen Reply is a feature that allows the bot to **listen to user replies** as a continuation of a conversation flow, **without relying on Telegram’s UI reply mechanism**.

This feature works by storing a **pending state** (cache-safe) per user. When the user sends the next message, the system decides whether the message should be treated as a **reply continuation** or as a **normal incoming message**.

---

## Core Concept

### Pending Scope
Pending state is stored using the scope:

- `chat:{chatId}:user:{userId}`

This ensures the state is:
- chat-specific
- user-specific
- isolated between different users

### TTL
Each pending state has a TTL (default: `120s`) which can be configured via:

- `telegram.console.listen_reply_ttl`

TTL can also be overridden when the library user explicitly activates Listen Reply.

---

## Two Listen Reply Modes

Listen Reply is designed with **two modes** to avoid conflicts between internal SDK behavior and custom user logic.

### 1. Inspector Mode (Owned by SDK)
Inspector Mode is used by the **Command Inspector** to complete incomplete command input.

Triggered when the resolver returns:
- `ResolveResult::MISSING_ARGUMENT`
- `ResolveResult::MISSING_OPTION`

Behavior:
1. The system stores a pending state with mode `inspector`, containing:
   - `baseInput` (command name, e.g. `make:model`)
   - `args` (arguments already collected)
   - `options` (option tokens already collected)
   - `next` (next target to ask: argument or option)
2. The system sends an interactive, localized prompt asking for the missing value.
3. When the user replies:
   - the reply is appended as the next argument, or
   - converted into `--option=value`
   - the command is rebuilt
   - the command is re-dispatched through the SDK CommandBus / runner

Important notes:
- **Inspector mode never calls `onReply()`**.
- Its sole purpose is to complete the command so it becomes valid and executable.

---

### 2. Custom Mode (Owned by Library User)
Custom Mode is intended for library users who want to build their own interactive flows (wizard, OTP, confirmation, etc).

This mode is activated explicitly by calling:

- `setMessageListenReply(...)`

Behavior:
1. The system stores a pending state with mode `custom`.
2. When the user sends the next plain text message:
   - the message is routed back to the target command
   - `ReplyContext` is activated (runtime-only)
   - the command handles the reply inside `onReply()`

Important notes:
- In custom mode, **the user has full control**:
  - The SDK does not rebuild commands.
  - The SDK does not send automatic responses.
- `onReply()` must be `void`, and response handling is entirely the responsibility of the library user.

---

## Command vs Plain Text Detection

The system relies on metadata provided by `TelegramContext`:

- `TelegramContext::$isCommand`

Routing rules:

### A. Incoming message is a COMMAND
- If a pending state exists:
  - the pending state is **automatically canceled**
- The system does not intercept
- The SDK continues handling the new command normally

Purpose:
- Prevent conflicts between an old pending state and a newly issued command.

---

### B. Incoming message is Plain Text
- If no pending state exists:
  - the SDK handles the message normally
- If a pending state exists:
  - `inspector` → rebuild and re-dispatch the command
  - `custom` → route to `onReply()`

---

## Prompt System (Inspector Mode)

When `MISSING_ARGUMENT` or `MISSING_OPTION` occurs, the Command Inspector can generate an interactive prompt.

### Dictionary Keys
Prompts use the following dictionary structure:

```php
'prompt' => [
  'default' => "Please enter a value for {type} *`{text}`*:",
],
'hint' => [
  'cancel' => "_Type `cancel` to cancel._",
],
```

## 🧭 Console Command Inspector (v4.1+)

Starting from **v4.1**, Telegram Bot Plus SDK introduces a **Console Command Inspector** layer.

Key features:

* Command validation before execution
* Automatic help messages (root / group / leaf)
* Structured error output
* Command suggestions
* Markdown-rendered output

### Multi-language Support

```php
'console' => [
    'lang' => env('TELEGRAM_LANG', 'en'),
    'lang_path' => null,
],
```

### Prompt Key Rules

- Argument key: `name`, `age`, etc.
- Option key: option long name without dashes (e.g. `--age` → `age`).

### Per-Command Prompt Override

Commands can override prompts using:

- `promptValue` (template strings)
- `promptVarible` (global default variables)

Example:
```php
$promptValue = [
  'mother' => 'What is the mother name for {vname}?',
  'where'  => 'Where does {vname} live?',
];

$promptVarible = [
  'vname' => 'you',
];
```

Result:

- `What is the mother name for you?`
- `Where does you live?`

Variables `{type}` and `{text}` are always injected automatically.
Additional variables (e.g. `{vname}`) come from `promptVarible` and can be overridden when needed.

### Design Notes

- Pending state is cache-safe (no closures or callable handlers).
- `ReplyContext` is runtime-only (not cached) and only active during custom mode dispatch.
- Listen Reply focuses purely on routing and state management and does not alter command business logic.

### 🔐 Command Authorization

The Command Inspector provides an **authorization** feature to control
whether a command can be accessed and executed by a user.

Authorization is implemented by defining a static `authorize()` method
on the command class.

~~~php
public static function authorize(TelegramUpdateMeta $meta): bool
{
    return $meta->actor()->isAdmin();
}
~~~

#### Authorization Use Cases

The authorization feature can be used to:

- Validate command access  
  (for example: user is not logged in, not an admin, or lacks permissions).
- Prevent execution of unauthorized commands.
- Filter which commands appear in the help output.

#### Authorization Behavior

- When `authorize()` returns `false`:
  - The command **cannot be executed**.
  - An *unauthorized* message will be displayed.
  - The command **will not appear** in the `--help` output.
- When `authorize()` returns `true`:
  - The command can be executed normally.
  - The command will appear in the help listing.

#### Important Notes

- The `authorize()` method must be **lightweight and deterministic**.
- Avoid heavy operations such as:
  - database queries
  - HTTP requests
  - file I/O
- Authorization is evaluated **before command execution**.

With this mechanism, the Command Inspector ensures that only
relevant and safe commands are visible and executable for the user.


---

## 🧹 Runtime Cache & Artisan Command

Internal SDK data is cached for performance:

* command registry
* middleware configuration
* console metadata
* language dictionaries

Clear all Telegram SDK cache with:

```bash
php artisan telegram:cache:clear
```

This command is safe and scoped only to Telegram SDK internals.


## ✨ Features

### 🔧 Development
- Auto-scan Telegram commands
- Opt-in auto command registration via metadata
- Flexible webhook URL normalization:
  - `webhook_url`
  - or `webhook_base_url` + `webhook_url_path`

### ➕ Extensions
- Telegram middleware system
- Middleware based on:
  - event
  - command
  - bot
- Artisan commands:
  - `telegram:webhook`
  - `telegram:sync`
  - `telegram:make:command`
  - `telegram:make:middleware`

---

## 📦 Installation

```bash
composer require dark-people/telegram-bot-plus-sdk
```

Optional config publish:

php artisan vendor:publish --tag=telegram-bot-config

## ⚙️ Configuration

Main config file: `config/telegram.php`

Basic example:

```php
return [
    'default' => 'bot1',

    'bots' => [
        'bot1' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        ],
    ],
];
```

## 🤖 Telegram Commands
### Create a Command
```bash
php artisan telegram:make:command StartCommand --name=start
```

Generated at:

```swift
app/Telegram/Commands/StartCommand.php
```

Example:

```php
final class StartCommand extends Command implements PlusCommandMeta
{
    use HasPlusCommandMeta;

    protected string $name = 'start';

    public bool $autoRegister = false;
    public string|array|null $forBot = null;
    public ?array $groups = null;
    public ?string $sharedAs = null;

    public function handle(): void
    {
        // command logic
    }
}
```

### Auto Registration

Commands are **not auto-registered by default.**

Enable explicitly:

```php
public bool $autoRegister = true;
```

Keep `autoRegister()` lightweight and deterministic.
Avoid DB queries, HTTP calls, or file I/O.

## 🧩 Telegram Middleware
### Create Middleware
```bash
php artisan telegram:make:middleware AuthMiddleware
```

Generated at:
```swift
app/Telegram/Middleware/AuthMiddleware.php
```

Example:
```php
final class AuthMiddleware implements TelegramMiddleware, PlusMiddlewareMeta
{
    use HasPlusMiddlewareMeta;

    public array $events = ['message'];
    public ?string $forBot = null;
    public ?array $commands = null;

    public function handle(TelegramContext $context): bool
    {
        return true;
    }
}
```

### ⚙️ Middleware via Configuration (Authoring)
#### Global Middleware

Key: `telegram.middleware`
```php
'middleware' => [
    'message' => [
        \App\Telegram\Middleware\LogMiddleware::class,
        ['class' => \App\Telegram\Middleware\AuthMiddleware::class, 'forBot' => 'bot1'],
    ],
    'command' => [
        '__all__' => [
            \App\Telegram\Middleware\AuthMiddleware::class,
        ],
    ],
],
```

#### Bot-Specific Middleware (STRICT)

Key: `telegram.bots.{bot}.middleware`
```php
'bots' => [
    'bot1' => [
        'middleware' => [
            'message' => [
                \App\Telegram\Middleware\AuthMiddleware::class,
            ],
        ],
    ],
],
```

### 🧠 Compiled Result (Internal)
```php
telegram.middleware_class = [
    'botName' => [
        'eventName' => [...],
        'command' => [
            '__all__' => [...],
            'start' => [...],
        ],
    ],
];
```

⚠️ middleware_class is compiled output, not for manual editing.


## 🧾 TelegramContext

`TelegramContext` provides runtime metadata:

- `eventName`
- `isCommand`
- `commandName`
- `arguments`
- `text`
- `botName`
- `update`
- `telegram`

It is:

- immutable (`readonly`)
- auto-resolved per webhook request


## 🌐 Webhook Flow

- Webhook request received
- Resolve botName from URL
- Read update from SDK
- Optional validation
- Create TelegramContext
- Run middleware
- Run commandsHandler

## 🧰 Facade

Use the `TelegramBot` facade:

```php
TelegramBot::bot()->sendMessage([
    'chat_id' => $chatId,
    'text' => 'Hello!',
]);
```

Webhook handler:
```php
TelegramBot::handler($request);
```

## TelegramUpdateMeta (Update Metadata)

This package provides `TelegramUpdateMeta`, a per-update metadata object that is built from the incoming Telegram update and registered into the Laravel container for the current update lifecycle.

It helps you answer questions like:
- Who is the actor (member/admin/bot)?
- What room type is this (group/supergroup/channel)?
- What action happened (message/join/leave/promoted/restricted/etc)?
- What changed (before/after) when Telegram provides old/new data?
- What permissions are available (by source + effective)?

### How it works

During update handling:
- A `TelegramUpdateAnalyzer` analyzes the current update (`TelegramContext`) and produces `TelegramUpdateMeta`.
- The resulting `TelegramUpdateMeta` is bound into the container (per update).
- Permissions are resolved using:
  - `PermissionCatalog` (permission keys + UI/group mapping)
  - `PermissionResolver` (builds `PermissionBag`: permissions by source + effective permissions)

### Consuming TelegramUpdateMeta

Recommended: constructor injection (works for commands, middleware, services).

```php
use DarkPeople\TelegramBot\Support\TelegramContext;
use DarkPeople\TelegramBot\Support\UpdateMeta\TelegramUpdateMeta;

final class MyCommand extends Command
{
    public function __construct(
        protected TelegramContext $ctx,
        protected TelegramUpdateMeta $meta,
    ) {}

    public function handle()
    {
        $action = $this->meta->action();
        $roomType = $this->meta->room()->roomType;
        $isAdmin = $this->meta->actor()->isAdmin();

        // Permission check (effective)
        $canSend = $this->meta->permissions()->can('can_send_messages');
    }
}
```

### PermissionBag (resolved permissions)
`TelegramUpdateMeta->permissions()` returns a `PermissionBag`:

- `effective()` final permissions
- `fromSource($source)` permissions per source
- `can($key)` convenience method

Tip: some UI permissions are grouped (e.g. "Send Media") while Telegram Bot API uses granular keys. `PermissionCatalog` contains UI/group mapping metadata to help produce user-friendly messages.


## 🏁 Conclusion

Telegram Bot Plus SDK focuses on:

- clarity over magic
- explicit opt-in behavior
- predictable runtime
- long-term maintainability

Built for developers who want full control over their Telegram bots.
