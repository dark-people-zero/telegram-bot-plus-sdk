# Telegram Bot Plus SDK (Laravel)

Telegram Bot Plus SDK adalah pengembangan dari [`irazasyed/telegram-bot-sdk`](https://github.com/irazasyed/telegram-bot-sdk)

yang menambahkan **auto command scan**, **middleware system**, dan **runtime pipeline** yang lebih terstruktur untuk aplikasi Laravel.

Library ini dirancang untuk:
- multi-bot
- scalable
- deterministic (compile-time & runtime jelas)
- minim konfigurasi manual

---

## ✨ Fitur Utama

### 🔧 Pengembangan
- Auto-scan command dari directory (`app/Telegram/Commands`)
- Auto-register command via metadata (opt-in)
- Normalisasi webhook URL:
  - `webhook_url`
  - atau kombinasi `webhook_base_url` + `webhook_url_path`

### ➕ Fitur Tambahan
- Middleware Telegram (sebelum command dijalankan)
- Middleware berbasis:
  - event
  - command
  - bot
- Artisan commands:
  - `telegram:webhook`
  - `telegram:sync`
  - `telegram:make:command`
  - `telegram:make:middleware`

---

## 📦 Instalasi

```bash
composer require darkpeople/telegram-bot-plus
```

## 🚀 Publish config (opsional):

```bash
php artisan vendor:publish --tag=telegram-bot-config
```

## ⚙️ Konfigurasi Dasar
File konfigurasi utama: `config/telegram.php`

Contoh minimal:

```bash
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

## 🤖 Command Telegram

### Membuat Command Baru

```bash
php artisan telegram:make:command StartCommand --name=start
```

File akan dibuat di:

```swift
app/Telegram/Commands/StartCommand.php
```

Contoh struktur command:

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
        // logic command
    }
}
```

### Auto Register Command
Command **tidak akan di-register otomatis** kecuali:
```bash
public bool $autoRegister = true;
```

### ⚠️ Catatan penting
`autoRegister()` dipanggil saat scan/boot:

- harus cepat
- deterministik
- **hindari** DB query, HTTP call, atau file I/O

## 🧩 Middleware Telegram

### Membuat Middleware

```bash
php artisan telegram:make:middleware AuthMiddleware
```

File akan dibuat di:

```swift
app/Telegram/Middleware/AuthMiddleware.php
```

Contoh middleware:

```php
final class AuthMiddleware implements TelegramMiddleware, PlusMiddlewareMeta
{
    use HasPlusMiddlewareMeta;

    public array $events = ['message'];
    public ?string $forBot = null;
    public ?array $commands = null;

    public function handle(TelegramContext $context): bool
    {
        // return false untuk menghentikan proses
        return true;
    }
}
```

## ⚙️ Middleware via Konfigurasi (Authoring)

Selain lewat metadata class, middleware **juga bisa didaftarkan via config**.

### 1️⃣ Global Middleware

Key: `telegram.middleware`

- Wajib event-map
- Item boleh:
  - string class
  - rule array `{ class, forBot }`
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
        'start' => [
            ['class' => \App\Telegram\Middleware\StartOnlyMiddleware::class],
        ],
    ],
],
```

### 2️⃣ Middleware per Bot (STRICT)

Key: `telegram.bots.{bot}.middleware`

- Wajib **event-map**
- Item **harus string class**
- Tidak boleh rule array
```php
'bots' => [
    'bot1' => [
        'token' => env('...'),
        'middleware' => [
            'message' => [
                \App\Telegram\Middleware\AuthMiddleware::class,
            ],
            'command' => [
                '__all__' => [
                    \App\Telegram\Middleware\LogMiddleware::class,
                ],
                'start' => [
                    \App\Telegram\Middleware\StartOnlyMiddleware::class,
                ],
            ],
        ],
    ],
],
```
### 🧠 Compile Result (Internal)

Semua middleware (scan + config) akan di-normalisasi menjadi:
```php
telegram.middleware_class = [
    'botName' => [
        'eventName' => [
            MiddlewareClass1,
            MiddlewareClass2,
        ],
        'command' => [
            '__all__' => [...],
            'start' => [...],
        ],
    ],
];
```

⚠️ `middleware_class` **bukan untuk di-edit manual** Ini adalah **hasil compile** yang dipakai runtime.


## 🧾 TelegramContext

`TelegramContext` berisi metadata runtime:

- `eventName`
- `isCommand`
- `commandName`
- `arguments`
- `text`
- `botName`
- `update`
- `telegram`

Context ini:

- immutable (`readonly`)
- otomatis di-resolve saat webhook hit

## 🌐 Webhook Flow

- Webhook masuk ke route
- Resolve `botName` dari URL
- Ambil update dari SDK
- Validasi webhook (optional)
- Buat `TelegramContext`
- Jalankan middleware
- Jalankan `commandsHandler`


## 🧰 Facade

Gunakan Facade `TelegramBot`:

```php
TelegramBot::bot()->sendMessage([
    'chat_id' => $chatId,
    'text' => 'Hello!',
]);
```


Atau untuk webhook:

```php
TelegramBot::handler($request);
```

## 🏁 Penutup

Telegram Bot Plus SDK dibuat untuk:

- developer yang ingin struktur rapi
- runtime yang jelas
- minim magic
- mudah di-maintain

Jika kamu butuh fleksibilitas lebih, library ini sengaja dibuat **opt-in**, bukan otomatis semuanya.

Happy hacking 🚀
