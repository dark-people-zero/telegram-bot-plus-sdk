<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\Permissions;

use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ActorMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\RoomMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ChangeMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\PermissionBag;
use DarkPeople\TelegramBot\Support\TelegramContext;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\TargetMeta;
use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\Update;

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
        ?TargetMeta $target,
    ): PermissionBag {
        // Keys applicable for this room type
        $keys = $this->keysForRoomType($room->type);

        // Split keys by scope (member vs admin), because their sources differ.
        $memberKeys = $this->keysForRoomType($room->type, PermissionCatalog::SCOPE_MEMBER);
        $adminKeys  = $this->keysForRoomType($room->type, PermissionCatalog::SCOPE_ADMIN);

        $bySource = [];
        $update = $ctx->update;

        // 1) Chat baseline member permissions (ChatPermissions) if present
        $chatPerm = $this->extractChatPermissions($update);
        if ($chatPerm) {
            $bySource[PermissionSource::CHAT_PERMISSIONS] = $this->fillKeys($memberKeys, $chatPerm);
        }

        // 2) Member status permissions (ChatMemberRestricted.permissions) if present
        $memberStatusPerm = $change->after;
        if ($memberStatusPerm) {
            $bySource[PermissionSource::MEMBER_STATUS] = $this->fillKeys($memberKeys, $memberStatusPerm->toArray());
        }

        // 3) Admin rights (ChatAdministratorRights fields on ChatMemberAdministrator/Creator) if present
        $adminRights = $this->extractAdminRights($change, $adminKeys);
        if ($adminRights) {
            $bySource[PermissionSource::ADMIN_RIGHTS] = $this->fillKeys($adminKeys, $adminRights);
        }

        // 4) Admin properties / pseudo keys (e.g. is_anonymous)
        $adminProps = $this->extractAdminRights($change, ['is_anonymous']);
        if ($adminProps) {
            // We only fill keys that exist in catalog; (pseudo) keys are still catalog keys
            $bySource[PermissionSource::ADMIN_PROPERTIES] = $this->fillKeys($adminKeys, $adminProps);
        }

        $effective = $this->mergeEffective($keys, $bySource, $actor, $target);

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
    protected function mergeEffective(array $keys, array $bySource, ActorMeta $actor, TargetMeta $target): array
    {
        $effective = [];
        $status = $target->role;

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
    protected function extractChatPermissions(Update $update): ?array
    {
        $chat = $update->getChat();
        if ($chat instanceof Chat && $chat->permissions) {
            return $chat->permissions->toArray();
        }
        
        return null;
    }

    /**
     * Extract admin rights (boolean flags) from ChatMemberAdministrator/Creator.
     * We filter only keys that exist in $adminKeys (from catalog).
     *
     * @param string[] $adminKeys
     * @return array<string, mixed>|null
     */
    protected function extractAdminRights(ChangeMeta $meta, array $adminKeys): ?array
    {
        $newChatMember = $meta->after;

        if(!$newChatMember) return null;

        $status = $newChatMember->status;
        
        if(!in_array($status, ['administrator', 'creator'], true)) return null;

        return $newChatMember->only($adminKeys)->toArray();
    }

}
