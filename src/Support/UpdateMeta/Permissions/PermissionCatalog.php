<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\Permissions;

final class PermissionCatalog
{
    // Scopes
    public const SCOPE_MEMBER = 'member'; // ChatPermissions (default member permissions / restrictions)
    public const SCOPE_ADMIN  = 'admin';  // Admin rights / admin properties

    // Room types
    public const ROOM_GROUP      = 'group';
    public const ROOM_SUPERGROUP = 'supergroup';
    public const ROOM_CHANNEL    = 'channel';

    /**
     * UI Toggle IDs (stable internal identifiers in your SDK).
     * These represent what Telegram shows in the UI.
     */
    public const UI_MEMBER_SEND_MESSAGES   = 'ui.member.send_messages';
    public const UI_MEMBER_SEND_MEDIA      = 'ui.member.send_media';
    public const UI_MEMBER_SEND_POLLS      = 'ui.member.send_polls';
    public const UI_MEMBER_SEND_OTHER      = 'ui.member.send_other_messages';
    public const UI_MEMBER_EMBED_LINKS     = 'ui.member.embed_links';

    public const UI_ADMIN_CHANGE_INFO      = 'ui.admin.change_info';
    public const UI_ADMIN_DELETE_MESSAGES  = 'ui.admin.delete_messages';
    public const UI_ADMIN_BAN_USERS        = 'ui.admin.ban_users';
    public const UI_ADMIN_INVITE_USERS     = 'ui.admin.invite_users';
    public const UI_ADMIN_MANAGE_TOPICS    = 'ui.admin.manage_topics';
    public const UI_ADMIN_PIN_MESSAGES     = 'ui.admin.pin_messages';
    public const UI_ADMIN_MANAGE_VIDEO     = 'ui.admin.manage_video_chats';
    public const UI_ADMIN_REMAIN_ANON      = 'ui.admin.remain_anonymous';
    public const UI_ADMIN_ADD_ADMINS       = 'ui.admin.add_new_admins';

    public const UI_ADMIN_POST_STORIES     = 'ui.admin.post_stories';
    public const UI_ADMIN_EDIT_STORIES     = 'ui.admin.edit_stories';
    public const UI_ADMIN_DELETE_STORIES   = 'ui.admin.delete_stories';

    /**
     * API Permission Key definitions (Telegram Bot API keys + a small set of pseudo keys).
     *
     * Fields:
     * - ui:      whether this key itself appears as a toggle (usually false for granular media keys)
     * - scope:   member|admin
     * - rooms:   applicable room types
     * - source:  ChatPermissions|ChatAdministratorRights|ChatMemberAdministrator (pseudo)
     * - label/hint: for user-facing messages
     */
    public function keyDefinitions(): array
    {
        $memberRooms = [self::ROOM_GROUP, self::ROOM_SUPERGROUP];

        return [
            // --------------------
            // MEMBER (ChatPermissions)
            // --------------------
            'can_send_messages' => [
                'ui' => true, // UI has "Send messages"
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send messages', 'id' => 'Kirim pesan'],
                'hint'  => ['en' => 'Allow users to send text messages.', 'id' => 'Izinkan pengguna mengirim pesan teks.'],
            ],

            // Media is grouped in UI, but API is granular:
            'can_send_photos' => [
                'ui' => false,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send photos', 'id' => 'Kirim foto'],
                'hint'  => ['en' => 'Allow users to send photos.', 'id' => 'Izinkan pengguna mengirim foto.'],
            ],
            'can_send_videos' => [
                'ui' => false,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send videos', 'id' => 'Kirim video'],
                'hint'  => ['en' => 'Allow users to send videos.', 'id' => 'Izinkan pengguna mengirim video.'],
            ],
            'can_send_audios' => [
                'ui' => false,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send music/audio', 'id' => 'Kirim audio'],
                'hint'  => ['en' => 'Allow users to send audio files.', 'id' => 'Izinkan pengguna mengirim file audio.'],
            ],
            'can_send_documents' => [
                'ui' => false,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send files', 'id' => 'Kirim file'],
                'hint'  => ['en' => 'Allow users to send files/documents.', 'id' => 'Izinkan pengguna mengirim file/dokumen.'],
            ],
            'can_send_voice_notes' => [
                'ui' => false,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send voice messages', 'id' => 'Kirim pesan suara'],
                'hint'  => ['en' => 'Allow users to send voice notes.', 'id' => 'Izinkan pengguna mengirim voice note.'],
            ],
            'can_send_video_notes' => [
                'ui' => false,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send video messages', 'id' => 'Kirim pesan video'],
                'hint'  => ['en' => 'Allow users to send video notes.', 'id' => 'Izinkan pengguna mengirim video note.'],
            ],

            'can_send_polls' => [
                'ui' => true, // UI has "Send polls"
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send polls', 'id' => 'Kirim polling'],
                'hint'  => ['en' => 'Allow users to create polls.', 'id' => 'Izinkan pengguna membuat polling.'],
            ],

            'can_send_other_messages' => [
                'ui' => true, // UI often shows this as "Stickers & GIFs"
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Send stickers & GIFs', 'id' => 'Kirim stiker & GIF'],
                'hint'  => ['en' => 'Allow stickers, GIFs, games, etc.', 'id' => 'Izinkan stiker, GIF, game, dll.'],
            ],

            'can_add_web_page_previews' => [
                'ui' => true, // UI shows "Embed links"
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'source' => 'ChatPermissions',
                'label' => ['en' => 'Embed links', 'id' => 'Sematkan tautan'],
                'hint'  => ['en' => 'Allow link previews.', 'id' => 'Izinkan pratinjau tautan.'],
            ],

            // --------------------
            // ADMIN (ChatAdministratorRights)
            // --------------------
            'can_change_info' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Change group info', 'id' => 'Ubah info grup'],
                'hint'  => ['en' => 'Allow changing chat info (title, photo, description).', 'id' => 'Izinkan mengubah info chat (judul, foto, deskripsi).'],
            ],

            'can_delete_messages' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Delete messages', 'id' => 'Hapus pesan'],
                'hint'  => ['en' => 'Allow deleting other users’ messages.', 'id' => 'Izinkan menghapus pesan pengguna lain.'],
            ],

            'can_restrict_members' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Ban users', 'id' => 'Blokir Pengguna'],
                'hint'  => ['en' => 'Allow restricting/banning members.', 'id' => 'Izinkan membatasi/memblokir anggota.'],
            ],

            'can_invite_users' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Add users', 'id' => 'Tambah Anggota'],
                'hint'  => ['en' => 'Allow adding/inviting users to the chat.', 'id' => 'Izinkan menambah/mengundang pengguna ke chat.'],
            ],

            'can_manage_topics' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_SUPERGROUP],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Manage topics', 'id' => 'Kelola topik'],
                'hint'  => ['en' => 'Allow managing forum topics.', 'id' => 'Izinkan mengelola topik forum.'],
            ],

            'can_pin_messages' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Pin messages', 'id' => 'Sematkan pesan'],
                'hint'  => ['en' => 'Allow pinning messages.', 'id' => 'Izinkan menyematkan pesan.'],
            ],

            'can_manage_video_chats' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Manage video chats', 'id' => 'Kelola obrolan video'],
                'hint'  => ['en' => 'Allow managing video chats/live streams.', 'id' => 'Izinkan mengelola obrolan video/siaran langsung.'],
            ],

            'can_promote_members' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Add new admins', 'id' => 'Menambahkan admin baru'],
                'hint'  => ['en' => 'Allow promoting members to admins.', 'id' => 'Izinkan mempromosikan anggota menjadi admin.'],
            ],

            // Stories rights (appear in newer Telegram UI)
            'can_post_stories' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Post stories', 'id' => 'Posting cerita'],
                'hint'  => ['en' => 'Allow posting stories on behalf of the chat.', 'id' => 'Izinkan memposting cerita atas nama chat.'],
            ],
            'can_edit_stories' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Edit others’ stories', 'id' => 'Edit cerita pengguna lain'],
                'hint'  => ['en' => 'Allow editing stories posted by other admins.', 'id' => 'Izinkan mengedit cerita yang diposting admin lain.'],
            ],
            'can_delete_stories' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'source' => 'ChatAdministratorRights',
                'label' => ['en' => 'Delete others’ stories', 'id' => 'Hapus cerita pengguna lain'],
                'hint'  => ['en' => 'Allow deleting stories posted by other admins.', 'id' => 'Izinkan menghapus cerita yang diposting admin lain.'],
            ],

            // PSEUDO KEY: UI toggle "Remain anonymous" is admin property (ChatMemberAdministrator.is_anonymous)
            'is_anonymous' => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'source' => 'ChatMemberAdministrator',
                'label' => ['en' => 'Remain anonymous', 'id' => 'Tetap Anonim'],
                'hint'  => ['en' => 'Admin actions appear as performed by the chat, not the admin user.', 'id' => 'Aksi admin tampil atas nama chat, bukan akun admin.'],
                'pseudo' => true,
            ],
        ];
    }

    /**
     * UI toggle definitions (what user sees).
     * Each UI toggle can map to one or many API keys.
     */
    public function uiDefinitions(): array
    {
        $memberRooms = [self::ROOM_GROUP, self::ROOM_SUPERGROUP];

        return [
            // MEMBER UI
            self::UI_MEMBER_SEND_MESSAGES => [
                'ui' => true,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'label' => ['en' => 'Send messages', 'id' => 'Kirim pesan'],
                'keys'  => ['can_send_messages'],
            ],
            self::UI_MEMBER_SEND_MEDIA => [
                'ui' => true,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'label' => ['en' => 'Send media', 'id' => 'Kirim media'],
                'keys'  => [
                    'can_send_photos',
                    'can_send_videos',
                    'can_send_audios',
                    'can_send_documents',
                    'can_send_voice_notes',
                    'can_send_video_notes',
                ],
            ],
            self::UI_MEMBER_SEND_POLLS => [
                'ui' => true,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'label' => ['en' => 'Send polls', 'id' => 'Kirim polling'],
                'keys'  => ['can_send_polls'],
            ],
            self::UI_MEMBER_SEND_OTHER => [
                'ui' => true,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'label' => ['en' => 'Send stickers & GIFs', 'id' => 'Kirim stiker & GIF'],
                'keys'  => ['can_send_other_messages'],
            ],
            self::UI_MEMBER_EMBED_LINKS => [
                'ui' => true,
                'scope' => self::SCOPE_MEMBER,
                'rooms' => $memberRooms,
                'label' => ['en' => 'Embed links', 'id' => 'Sematkan tautan'],
                'keys'  => ['can_add_web_page_previews'],
            ],

            // ADMIN UI (matches your observed labels)
            self::UI_ADMIN_CHANGE_INFO => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'label' => ['en' => 'Change group info', 'id' => 'Ubah info grup'],
                'keys'  => ['can_change_info'],
            ],
            self::UI_ADMIN_DELETE_MESSAGES => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'label' => ['en' => 'Delete messages', 'id' => 'Hapus pesan'],
                'keys'  => ['can_delete_messages'],
            ],
            self::UI_ADMIN_BAN_USERS => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'label' => ['en' => 'Ban users', 'id' => 'Blokir Pengguna'],
                'keys'  => ['can_restrict_members'],
            ],
            self::UI_ADMIN_INVITE_USERS => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'label' => ['en' => 'Add users', 'id' => 'Tambah Anggota'],
                'keys'  => ['can_invite_users'],
            ],
            self::UI_ADMIN_MANAGE_TOPICS => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_SUPERGROUP],
                'label' => ['en' => 'Manage topics', 'id' => 'Kelola topik'],
                'keys'  => ['can_manage_topics'],
            ],
            self::UI_ADMIN_PIN_MESSAGES => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'label' => ['en' => 'Pin messages', 'id' => 'Sematkan pesan'],
                'keys'  => ['can_pin_messages'],
            ],
            self::UI_ADMIN_MANAGE_VIDEO => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'label' => ['en' => 'Manage video chats', 'id' => 'Kelola obrolan video'],
                'keys'  => ['can_manage_video_chats'],
            ],
            self::UI_ADMIN_REMAIN_ANON => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'label' => ['en' => 'Remain anonymous', 'id' => 'Tetap Anonim'],
                'keys'  => ['is_anonymous'], // pseudo key
            ],
            self::UI_ADMIN_ADD_ADMINS => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP],
                'label' => ['en' => 'Add new admins', 'id' => 'Menambahkan admin baru'],
                'keys'  => ['can_promote_members'],
            ],

            // Stories
            self::UI_ADMIN_POST_STORIES => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'label' => ['en' => 'Post stories', 'id' => 'Posting cerita'],
                'keys'  => ['can_post_stories'],
            ],
            self::UI_ADMIN_EDIT_STORIES => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'label' => ['en' => 'Edit others’ stories', 'id' => 'Edit cerita pengguna lain'],
                'keys'  => ['can_edit_stories'],
            ],
            self::UI_ADMIN_DELETE_STORIES => [
                'ui' => true,
                'scope' => self::SCOPE_ADMIN,
                'rooms' => [self::ROOM_GROUP, self::ROOM_SUPERGROUP, self::ROOM_CHANNEL],
                'label' => ['en' => 'Delete others’ stories', 'id' => 'Hapus cerita pengguna lain'],
                'keys'  => ['can_delete_stories'],
            ],
        ];
    }

    // -----------------------
    // Convenience helpers
    // -----------------------

    public function allKeys(): array
    {
        return array_keys($this->keyDefinitions());
    }

    public function keyMeta(string $key): ?array
    {
        $defs = $this->keyDefinitions();
        return $defs[$key] ?? null;
    }

    public function uiMeta(string $uiId): ?array
    {
        $defs = $this->uiDefinitions();
        return $defs[$uiId] ?? null;
    }

    public function uiLabel(string $uiId, string $lang = 'en'): ?string
    {
        return $this->uiDefinitions()[$uiId]['label'][$lang] ?? null;
    }

    public function uiKeys(string $uiId): array
    {
        return $this->uiDefinitions()[$uiId]['keys'] ?? [];
    }

    /**
     * Build a human message when a permission key is missing/false.
     * If the key belongs to a UI group (like media), you can use uiId instead.
     */
    public function keyLabel(string $key, string $lang = 'en'): ?string
    {
        return $this->keyDefinitions()[$key]['label'][$lang] ?? null;
    }

    public function keyHint(string $key, string $lang = 'en'): ?string
    {
        return $this->keyDefinitions()[$key]['hint'][$lang] ?? null;
    }

    /**
     * For "send media" grouping: return the UI ID that contains this key (if any).
     */
    public function uiIdForKey(string $key): ?string
    {
        foreach ($this->uiDefinitions() as $uiId => $meta) {
            if (in_array($key, $meta['keys'] ?? [], true)) {
                return $uiId;
            }
        }
        return null;
    }
}
