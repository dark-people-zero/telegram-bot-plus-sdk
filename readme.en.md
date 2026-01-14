
# Telegram Bot Plus SDK (Laravel)

Telegram Bot Plus SDK is an extension of [`irazasyed/telegram-bot-sdk`](https://github.com/irazasyed/telegram-bot-sdk)

that adds **auto command scanning**, **Telegram middleware**, and a **structured runtime pipeline** for Laravel applications.

Designed for:
- multi-bot setups
- scalability
- deterministic boot & runtime
- minimal manual configuration

---

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
composer require darkpeople/telegram-bot-plus
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


## 🏁 Conclusion

Telegram Bot Plus SDK focuses on:

- clarity over magic
- explicit opt-in behavior
- predictable runtime
- long-term maintainability

Built for developers who want full control over their Telegram bots.
