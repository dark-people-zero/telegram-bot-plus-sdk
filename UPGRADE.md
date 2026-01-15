# Upgrade Guide

## Upgrade ke v3.1.0

Versi `v3.1.0` menambahkan sistem metadata update dan permission yang lebih terstruktur.

Upgrade ini `tidak breaking` untuk penggunaan dasar, tetapi ada fitur baru yang bisa kamu manfaatkan.

## Perubahan Utama
### ✨ Fitur Baru

- `TelegramUpdateMeta`<br>
  Metadata hasil analisis update Telegram (actor, room, action, permissions, dll).

- `TelegramUpdateAnalyzer`<br>
Menganalisis update dan membangun TelegramUpdateMeta.

- Sistem permission baru:
  - `PermissionCatalog`
  - `PermissionResolver`
  - `PermissionBag`

### Binding Otomatis

Mulai versi ini:

- `TelegramBotServiceProvider`:
  - mendaftarkan `PermissionCatalog`
  - mendaftarkan `PermissionResolver`
- `BotsManagerPlus`:
  - membuat `TelegramUpdateAnalyzer`
  - mendaftarkan `TelegramUpdateMeta` **per update**

Tidak ada konfigurasi tambahan yang perlu kamu lakukan.

### Cara Migrasi (Opsional)
### Jika sebelumnya kamu membaca permission manual dari update

❌ Sebelum:
```php
$permissions = $update['message']['chat']['permissions'] ?? [];
```

✅ Sekarang (direkomendasikan):
```php
$canSend = $meta->permissions()->can('can_send_messages');
```

### Jika kamu ingin membuat pesan error ke user

Gunakan metadata dari `PermissionCatalog`:
- label UI (EN / ID)
- hint penjelasan permission

Ini membuat pesan ke user lebih **konsisten dengan UI Telegram**.

### Tidak Ada Breaking Change
- Command lama tetap berjalan.
- Middleware lama tetap berjalan.
- Tidak ada perubahan signature command / middleware.

Fitur baru ini **opt-in**:
kamu hanya menggunakannya jika memang dibutuhkan.

### Rekomendasi Penggunaan

Versi ini paling optimal jika kamu:
- ingin memvalidasi permission user & bot
- ingin membuat guard / policy yang konsisten
- ingin memberikan pesan error yang jelas ke admin / user

## From v2.x to v3.0.0

### TelegramEvents namespace change

Update your imports:

```php
// Old
use DarkPeople\TelegramBot\Src\Contracts\TelegramEvents;

// New
use DarkPeople\TelegramBot\Contracts\TelegramEvents;
```

## From v1.x to v2.0.0

### TelegramEvents namespace change

Update your imports:

```php
// Old
use Packages\DarkPeople\TelegramBot\Src\Contracts\TelegramEvents;

// New
use DarkPeople\TelegramBot\Src\Contracts\TelegramEvents;
```