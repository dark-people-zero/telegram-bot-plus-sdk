# Telegram Bot Plus SDK (Laravel)

Telegram Bot Plus SDK adalah pengembangan dari [`irazasyed/telegram-bot-sdk`](https://github.com/irazasyed/telegram-bot-sdk)

yang menambahkan **auto command scan**, **middleware system**, dan **runtime pipeline** yang lebih terstruktur untuk aplikasi Laravel.

Library ini dirancang untuk:
- multi-bot
- scalable
- deterministic (compile-time & runtime jelas)
- minim konfigurasi manual

---

# Listen Reply (Interactive Reply Listener)

Listen Reply adalah fitur untuk “mendengarkan balasan user” sebagai kelanjutan alur percakapan bot, **tanpa** bergantung pada UI Telegram “reply message”.

Fitur ini bekerja dengan menyimpan **state pending** (cache-safe) per user, lalu ketika user mengirim pesan berikutnya, sistem akan memutuskan apakah pesan tersebut harus diproses sebagai **balasan** atau sebagai **pesan normal**.

---

## Konsep Utama

### Scope Pending
State pending disimpan dengan scope:

- `chat:{chatId}:user:{userId}`

Sehingga state bersifat:
- per chat
- per user
- tidak saling bentrok antar user

### TTL
Pending state memiliki TTL (default `120s`) yang bisa diatur melalui config:

- `telegram.console.listen_reply_ttl`

TTL juga bisa di-override ketika pengguna library memanggil API Listen Reply.

---

## Dua Mode Listen Reply

Listen Reply memiliki 2 mode agar alur internal SDK tidak bentrok dengan alur kustom milik pengguna library.

### 1. Inspector Mode (Owned by SDK)
Inspector Mode dipakai oleh **Command Inspector** untuk menyelesaikan input command yang belum lengkap.

Dipicu ketika resolver menghasilkan status:
- `ResolveResult::MISSING_ARGUMENT`
- `ResolveResult::MISSING_OPTION`

Perilaku:
1. Sistem menyimpan pending state mode `inspector`:
   - `baseInput` (command name, format `make:model`)
   - `args` (arg yang sudah terkumpul)
   - `options` (token option yang sudah terkumpul)
   - `next` (target yang akan ditanyakan: arg/opt)
2. Sistem mengirim prompt (multi-language) untuk meminta nilai berikutnya.
3. Ketika user membalas:
   - sistem menggabungkan balasan user ke `args` atau membentuk `--opt=value`
   - sistem membangun ulang command
   - sistem melakukan dispatch ulang melalui jalur CommandBus / runner SDK

Catatan penting:
- **Inspector mode tidak memanggil `onReply()`**.
- Tujuannya murni untuk melengkapi command agar valid dan bisa dieksekusi.

---

### 2. Custom Mode (Owned by Library User)
Custom Mode dipakai oleh pengguna library ketika ingin membuat alur interaktif sendiri (wizard / OTP / konfirmasi, dsb).

Pengguna library mengaktifkan mode ini dengan memanggil:

- `setMessageListenReply(...)`

Perilaku:
1. Sistem menyimpan pending state mode `custom` (cache-safe).
2. Saat user mengirim pesan berikutnya (plain text):
   - sistem meroute pesan tersebut kembali ke command target
   - sistem mengaktifkan `ReplyContext` (runtime-only)
   - command dapat menangani balasan di method `onReply()`

Catatan penting:
- Dalam custom mode, **user full control**:
  - SDK tidak membangun ulang command.
  - SDK tidak memberikan respon otomatis.
- `onReply()` bersifat `void`, dan respon sepenuhnya diatur pengguna library.

---

## Deteksi Command vs Plain Text

Sistem menggunakan metadata dari `TelegramContext`:

- `TelegramContext::$isCommand`

Aturan routing:

### A. Jika pesan adalah COMMAND
- Jika user mengirim command baru saat masih ada pending:
  - pending state **dibatalkan** (auto cancel)
  - sistem tidak mengintersep, biarkan SDK menangani command baru normal

Tujuan:
- mencegah tabrakan state lama dengan command baru.

### B. Jika pesan adalah Plain Text
- Jika tidak ada pending:
  - biarkan SDK memproses normal
- Jika ada pending:
  - `inspector`: rebuild + dispatch ulang
  - `custom`: route ke `onReply()`

---

## Prompt System (Inspector Mode)

Saat terjadi `MISSING_ARGUMENT` / `MISSING_OPTION`, Command Inspector dapat membuat prompt interaktif.

### Dictionary Keys
Prompt menggunakan dictionary:

- `prompt.default`
- `hint.cancel`

Format contoh:

```php
'prompt' => [
  'default' => "Masukkan nilai untuk {type} *`{text}`*:",
],
'hint' => [
  'cancel' => "_Ketik `cancel` untuk membatalkan._",
],
```

### Prompt Key Rules

- Argument key: `name`, `age`, dst.
- Option key: tanpa dash, contoh `--age` => `age`.

### Prompt Override per Command

Command dapat meng-override prompt dengan:
- `promptValue` (template string)
- `promptVarible` (global default variables)

Contoh:
```php
$promptValue = [
  'mother' => 'Nama ibu untuk {vname} siapa ?',
  'where'  => '{vname} tinggal dimana ?',
];

$promptVarible = [
  'vname' => 'kamu',
];
```

Output:

- `Nama ibu untuk kamu siapa ?`
- `kamu tinggal dimana ?`

Variable `{type}` dan `{text}` selalu tersedia otomatis.
Variable lain (contoh `{vname}`) diambil dari `promptVarible` dan dapat di-override oleh sistem bila diperlukan.

### Catatan Desain

- Pending state bersifat cache-safe (tidak menyimpan closure / handler).
- `ReplyContext` adalah runtime-only (tidak di-cache), hanya aktif saat custom mode dispatch.
- Listen Reply hanya melakukan routing dan state management, tidak mengubah business logic command.

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
