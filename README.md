# Telegram Bot Plus SDK (Laravel)

Telegram Bot Plus SDK adalah pengembangan dari [`irazasyed/telegram-bot-sdk`](https://github.com/irazasyed/telegram-bot-sdk)

yang menambahkan **auto command scan**, **middleware system**, dan **runtime pipeline** yang lebih terstruktur untuk aplikasi Laravel.

Library ini dirancang untuk:
- multi-bot
- scalable
- deterministic (compile-time & runtime jelas)
- minim konfigurasi manual

---

## 🧭 Console Command Inspector (v4.1+)

Mulai versi **v4.1**, Telegram Bot Plus SDK menyediakan sistem **Console Command Inspector**.

Fitur utama:

* Validasi command sebelum dieksekusi
* Help message otomatis (root / group / command)
* Pesan error terstruktur
* Suggestion jika command salah
* Output Markdown (siap dikirim ke Telegram)

### Multi-language

```php
'console' => [
    'lang' => env('TELEGRAM_LANG', 'id'),
    'lang_path' => null,
],
```

### 🔐 Command Authorization

Command Inspector menyediakan fitur **authorization** untuk mengontrol
apakah sebuah command boleh diakses dan dieksekusi oleh user.

Authorization dilakukan dengan menambahkan method static `authorize()` pada command.

~~~php
public static function authorize(TelegramUpdateMeta $meta): bool
{
    return $meta->actor()->isAdmin();
}
~~~

#### Kegunaan Authorization

Fitur authorization digunakan untuk:

- Melakukan validasi akses command  
  (misalnya: user belum login, bukan admin, atau tidak memiliki izin tertentu).
- Mencegah eksekusi command yang tidak diizinkan.
- Menyaring daftar command yang ditampilkan pada help.

#### Perilaku Authorization

- Jika `authorize()` mengembalikan `false`:
  - Command **tidak dapat dieksekusi**.
  - Sistem akan menampilkan pesan *unauthorized*.
  - Command **tidak akan muncul** pada hasil `--help`.
- Jika `authorize()` mengembalikan `true`:
  - Command dapat dieksekusi seperti biasa.
  - Command akan ditampilkan pada daftar help.

#### Catatan Penting

- Method `authorize()` harus bersifat **ringan dan deterministik**.
- Hindari operasi berat seperti:
  - query database
  - HTTP request
  - file I/O
- Authorization dievaluasi **sebelum eksekusi command**.

Dengan mekanisme ini, Command Inspector hanya akan menampilkan
dan mengeksekusi command yang relevan dan aman sesuai dengan konteks user.


---

## 🧹 Cache Internal & Artisan Command

Semua data internal SDK dicache otomatis:

* command registry
* middleware config
* console metadata
* language dictionary

Untuk membersihkan cache:

```bash
php artisan telegram:cache:clear
```

Aman dipanggil kapan saja dan tidak mempengaruhi cache aplikasi lain.


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
composer require dark-people/telegram-bot-plus-sdk
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

## TelegramUpdateMeta (Metadata Update Telegram)
Mulai versi **v3.1.0**, package ini menyediakan `TelegramUpdateMeta`, yaitu objek metadata hasil analisis dari setiap update Telegram yang masuk.

`TelegramUpdateMeta` dirancang untuk menjawab pertanyaan seperti:

- Siapa actor yang melakukan aksi? (member / admin / bot)
- Aksi apa yang terjadi? (pesan, join, leave, promote, restrict, dll)
- Di room apa update terjadi? (group / supergroup / channel)
- Apakah ada perubahan data (before / after)?
- Permission apa saja yang tersedia dan efektif?

Objek ini **dibuat per update** dan **di-bind ke Laravel container**, sehingga bisa di-consume dengan dependency injection di:

- Command
- Middleware
- Service lain

### Cara Kerja Singkat

Alur saat update Telegram diproses:
- `TelegramContext` dibuat untuk update yang sedang diproses.
- `TelegramUpdateAnalyzer` menganalisis update tersebut.
- Hasil analisis disimpan sebagai `TelegramUpdateMeta`.
- `TelegramUpdateMeta` di-register ke container (per update).
- Middleware / Command dapat langsung menggunakannya.

Semua proses ini:
- ✅ cepat
- ✅ deterministik
- ❌ tanpa network call tambahan

### Menggunakan TelegramUpdateMeta

Disarankan menggunakan **constructor injection**.
```php
use DarkPeople\TelegramBot\Support\TelegramContext;
use DarkPeople\TelegramBot\Support\UpdateMeta\TelegramUpdateMeta;

class MyCommand extends Command
{
    public function __construct(
        protected TelegramContext $context,
        protected TelegramUpdateMeta $meta,
    ) {}

    public function handle()
    {
        $action   = $this->meta->action();
        $roomType = $this->meta->room()->roomType;
        $isAdmin  = $this->meta->actor()->isAdmin();

        if (! $this->meta->permissions()->can('can_send_messages')) {
            // user tidak diizinkan mengirim pesan
        }
    }
}
```
### Sistem Permission
### PermissionCatalog

`PermissionCatalog` adalah kamus **permission Telegram** yang berisi:
- daftar permission key (API)
- grouping sesuai UI Telegram (misalnya "Kirim Media")
- label UI (Bahasa Inggris & Indonesia)
- scope permission:
  - `member` → pengaturan permission anggota
  - `admin` → hak admin

Class ini **tidak melakukan pengecekan**, hanya mendefinisikan:

>*"Permission apa saja yang ada dan bagaimana menjelaskannya ke user."*

### PermissionResolver & PermissionBag

`PermissionResolver` menggunakan data dari update Telegram untuk menghasilkan `PermissionBag`, yang berisi:

- permission per sumber:
  - chat permissions
  - status member
  - admin rights
- permission efektif (final)

`PermissionBag` dapat diakses melalui:
```php
$permissions = $meta->permissions();

$permissions->can('can_send_messages');
$permissions->effective();
$permissions->fromSource('chat_permissions');
```
### Catatan Penting

- `TelegramUpdateMeta` bukan policy engine.
- Ia menyediakan data yang dibutuhkan untuk membangun:
  - rule
  - guard
  - policy
  - middleware keputusan

Dengan kata lain:

>**Meta + Resolver = data**<br>
**Keputusan = di tangan aplikasi kamu**


## 🏁 Penutup

Telegram Bot Plus SDK dibuat untuk:

- developer yang ingin struktur rapi
- runtime yang jelas
- minim magic
- mudah di-maintain

Jika kamu butuh fleksibilitas lebih, library ini sengaja dibuat **opt-in**, bukan otomatis semuanya.

Happy hacking 🚀
