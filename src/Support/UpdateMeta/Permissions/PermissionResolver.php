<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\Permissions;

use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ActorMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\RoomMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ChangeMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\PermissionBag;
use DarkPeople\TelegramBot\Support\TelegramContext;

final class PermissionResolver
{
    public function __construct(
        protected PermissionCatalog $catalog,
    ) {}

    /**
     * Resolve permissions into a PermissionBag:
     * - catalogKeys: all known keys (for this room type)
     * - bySource:    permissions grouped by source
     * - effective:   merged final permissions (best-effort)
     *
     * IMPORTANT:
     * - No network calls (fast & deterministic).
     * - Uses only incoming update payload (ChatPermissions, chat_member/my_chat_member).
     */
    public function resolve(
        TelegramContext $ctx,
        ActorMeta $actor,
        RoomMeta $room,
        ?ChangeMeta $change,
        mixed $update = null,
    ): PermissionBag {
        // Keys applicable for this room type
        $keys = $this->keysForRoomType($room->roomType);

        // Split keys by scope (member vs admin), because their sources differ.
        $memberKeys = $this->keysForRoomType($room->roomType, PermissionCatalog::SCOPE_MEMBER);
        $adminKeys  = $this->keysForRoomType($room->roomType, PermissionCatalog::SCOPE_ADMIN);

        $bySource = [];

        // 1) Chat baseline member permissions (ChatPermissions) if present
        $chatPerm = $this->extractChatPermissions($update);
        if ($chatPerm) {
            $bySource[PermissionSource::CHAT_PERMISSIONS] = $this->fillKeys($memberKeys, $chatPerm);
        }

        // 2) Member status permissions (ChatMemberRestricted.permissions) if present
        $memberStatusPerm = $this->extractMemberStatusPermissions($update);
        if ($memberStatusPerm) {
            $bySource[PermissionSource::MEMBER_STATUS] = $this->fillKeys($memberKeys, $memberStatusPerm);
        }

        // 3) Admin rights (ChatAdministratorRights fields on ChatMemberAdministrator/Creator) if present
        $adminRights = $this->extractAdminRights($update, $adminKeys);
        if ($adminRights) {
            $bySource[PermissionSource::ADMIN_RIGHTS] = $this->fillKeys($adminKeys, $adminRights);
        }

        // 4) Admin properties / pseudo keys (e.g. is_anonymous)
        $adminProps = $this->extractAdminProperties($update);
        if ($adminProps) {
            // We only fill keys that exist in catalog; (pseudo) keys are still catalog keys
            $bySource[PermissionSource::ADMIN_PROPERTIES] = $this->fillKeys($adminKeys, $adminProps);
        }

        $effective = $this->mergeEffective($keys, $bySource, $actor, $room, $update);

        return new PermissionBag(
            catalogKeys: $keys,
            bySource: $bySource,
            effective: $effective,
        );
    }

    /**
     * Get keys from PermissionCatalog by room type, optionally filtered by scope.
     *
     * @return string[]
     */
    protected function keysForRoomType(string $roomType, ?string $scope = null): array
    {
        $defs = $this->catalog->keyDefinitions();

        $out = [];
        foreach ($defs as $key => $meta) {
            $rooms = $meta['rooms'] ?? [];
            if (!in_array($roomType, $rooms, true)) {
                continue;
            }
            if ($scope !== null && ($meta['scope'] ?? null) !== $scope) {
                continue;
            }
            $out[] = $key;
        }

        return $out;
    }

    /**
     * Merge precedence:
     * - Admin: prefer ADMIN_RIGHTS then admin_properties, then member_status, then chat_permissions
     * - Non-admin: prefer MEMBER_STATUS then chat_permissions
     *
     * Also handles statuses: left/kicked => force most permissions false.
     */
    protected function mergeEffective(array $keys, array $bySource, ActorMeta $actor, RoomMeta $room, mixed $update): array
    {
        $effective = [];
        $status = $this->extractNewMemberStatus($update);

        $isAdminish = $actor->isAdmin();

        // Order differs depending on whether the actor is adminish.
        $order = [];
        if ($isAdminish) {
            if (isset($bySource[PermissionSource::ADMIN_RIGHTS])) $order[] = PermissionSource::ADMIN_RIGHTS;
            if (isset($bySource[PermissionSource::ADMIN_PROPERTIES])) $order[] = PermissionSource::ADMIN_PROPERTIES;
        }
        if (isset($bySource[PermissionSource::MEMBER_STATUS])) $order[] = PermissionSource::MEMBER_STATUS;
        if (isset($bySource[PermissionSource::CHAT_PERMISSIONS])) $order[] = PermissionSource::CHAT_PERMISSIONS;

        foreach ($keys as $k) {
            $val = null;

            foreach ($order as $src) {
                if (!isset($bySource[$src])) continue;
                if (!array_key_exists($k, $bySource[$src])) continue;

                $candidate = $bySource[$src][$k];
                if ($candidate !== null) {
                    $val = (bool) $candidate;
                    break;
                }
            }

            // If the target member is kicked/left, assume no meaningful permissions.
            // (For bot/member checks, you can override later.)
            if (in_array($status, ['kicked', 'left'], true)) {
                $val = false;
            }

            $effective[$k] = $val;
        }

        return $effective;
    }

    /**
     * @param string[] $keys
     * @param array<string, mixed> $data
     * @return array<string, bool|null>
     */
    protected function fillKeys(array $keys, array $data): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = array_key_exists($k, $data) ? $this->toBoolOrNull($data[$k]) : null;
        }
        return $out;
    }

    protected function toBoolOrNull(mixed $v): ?bool
    {
        if ($v === null) return null;
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return (bool) ((int) $v);
        if (is_string($v)) {
            $vv = strtolower($v);
            if (in_array($vv, ['true', '1', 'yes'], true)) return true;
            if (in_array($vv, ['false', '0', 'no'], true)) return false;
        }
        return null;
    }

    /**
     * Extract chat.permissions (ChatPermissions) from update (best-effort).
     * @return array<string, mixed>|null
     */
    protected function extractChatPermissions(mixed $update): ?array
    {
        $chat = $this->get($update, 'message.chat', null)
            ?? $this->get($update, 'edited_message.chat', null)
            ?? $this->get($update, 'channel_post.chat', null)
            ?? $this->get($update, 'edited_channel_post.chat', null)
            ?? $this->get($update, 'callback_query.message.chat', null)
            ?? $this->get($update, 'chat_member.chat', null)
            ?? $this->get($update, 'my_chat_member.chat', null);

        $perm = $this->get($chat, 'permissions', null);
        $arr = $this->normalize($perm);

        return $arr ?: null;
    }

    /**
     * Extract ChatMemberRestricted.permissions from chat_member/my_chat_member.
     * @return array<string, mixed>|null
     */
    protected function extractMemberStatusPermissions(mixed $update): ?array
    {
        $new = $this->get($update, 'chat_member.new_chat_member', null)
            ?? $this->get($update, 'my_chat_member.new_chat_member', null);

        $perm = $this->get($new, 'permissions', null);
        $arr = $this->normalize($perm);

        return $arr ?: null;
    }

    /**
     * Extract admin rights (boolean flags) from ChatMemberAdministrator/Creator.
     * We filter only keys that exist in $adminKeys (from catalog).
     *
     * @param string[] $adminKeys
     * @return array<string, mixed>|null
     */
    protected function extractAdminRights(mixed $update, array $adminKeys): ?array
    {
        $new = $this->get($update, 'chat_member.new_chat_member', null)
            ?? $this->get($update, 'my_chat_member.new_chat_member', null);

        $status = $this->get($new, 'status', null);
        if (!in_array($status, ['administrator', 'creator'], true)) {
            return null;
        }

        $arr = $this->normalize($new);
        if (!$arr) return null;

        $rights = [];
        foreach ($adminKeys as $k) {
            if (array_key_exists($k, $arr)) {
                $rights[$k] = $arr[$k];
            }
        }

        return $rights ?: null;
    }

    /**
     * Extract admin-only properties that aren't "ChatAdministratorRights" booleans,
     * but still relevant in UI (pseudo keys), like: is_anonymous.
     *
     * @return array<string, mixed>|null
     */
    protected function extractAdminProperties(mixed $update): ?array
    {
        $new = $this->get($update, 'chat_member.new_chat_member', null)
            ?? $this->get($update, 'my_chat_member.new_chat_member', null);

        $status = $this->get($new, 'status', null);
        if (!in_array($status, ['administrator', 'creator'], true)) {
            return null;
        }

        $arr = $this->normalize($new);
        if (!$arr) return null;

        // Pseudo/UI property
        $props = [];
        if (array_key_exists('is_anonymous', $arr)) {
            $props['is_anonymous'] = $arr['is_anonymous'];
        }

        return $props ?: null;
    }

    protected function extractNewMemberStatus(mixed $update): ?string
    {
        $new = $this->get($update, 'chat_member.new_chat_member', null)
            ?? $this->get($update, 'my_chat_member.new_chat_member', null);

        $status = $this->get($new, 'status', null);
        return is_string($status) ? $status : null;
    }

    // --- access helpers (array/object hybrid) ---

    protected function get(mixed $data, string $path, mixed $default = null): mixed
    {
        if ($data === null) return $default;

        $parts = explode('.', $path);
        $cur = $data;

        foreach ($parts as $p) {
            if (is_array($cur)) {
                if (!array_key_exists($p, $cur)) return $default;
                $cur = $cur[$p];
                continue;
            }

            if (is_object($cur)) {
                if ($cur instanceof \ArrayAccess && isset($cur[$p])) {
                    $cur = $cur[$p];
                    continue;
                }
                if (isset($cur->{$p})) {
                    $cur = $cur->{$p};
                    continue;
                }
                $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $p)));
                if (method_exists($cur, $getter)) {
                    $cur = $cur->{$getter}();
                    continue;
                }
                return $default;
            }

            return $default;
        }

        return $cur;
    }

    protected function normalize(mixed $data): array
    {
        if ($data === null) return [];
        if (is_array($data)) return $data;

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) return (array) $data->toArray();
            return json_decode(json_encode($data), true) ?: [];
        }

        return [];
    }
}
