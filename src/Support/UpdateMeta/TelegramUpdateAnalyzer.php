<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta;

use DarkPeople\TelegramBot\Support\UpdateMeta\Permissions\PermissionResolver;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ActorMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\RoomMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ChangeMeta;
use DarkPeople\TelegramBot\Support\TelegramContext;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\TargetMeta;

final class TelegramUpdateAnalyzer
{
    public function __construct(
        protected PermissionResolver $permissionResolver,
    ) {}

    public function analyze(TelegramContext $ctx): TelegramUpdateMeta
    {
        $update = $ctx->update->all();

        $action = $this->detectAction($update);
        $target = $this->extractTarget($update);

        $room = $this->extractRoom($update);
        $actor = $this->extractActor($update, $room);

        $change = $this->extractChange($update);

        $permissions = $this->permissionResolver->resolve($ctx, $actor, $room, $change, $update);

        return new TelegramUpdateMeta(
            action: $action,
            actor: $actor,
            target: $target,
            room: $room,
            change: $change,
            permissions: $permissions,
            raw: [
                'update_type' => $this->detectUpdateType($update),
            ],
        );
    }

    /**
     * Detects a normalized "update type" based on common Telegram update keys.
     */
    protected function detectUpdateType(mixed $update): string
    {
        foreach ([
            'message',
            'edited_message',
            'channel_post',
            'edited_channel_post',
            'callback_query',
            'inline_query',
            'chosen_inline_result',
            'chat_member',
            'my_chat_member',
            'chat_join_request',
            'poll',
            'poll_answer',
            'pre_checkout_query',
            'shipping_query',
            'business_message',
            'edited_business_message',
        ] as $k) {
            if ($this->has($update, $k)) return $k;
        }
        return 'unknown';
    }

    /**
     * Your internal "action" taxonomy.
     */
    protected function detectAction(mixed $update): string
    {
        $type = $this->detectUpdateType($update);

        // Membership change updates are best for join/leave/promote/restrict, etc.
        if (in_array($type, ['chat_member', 'my_chat_member'], true)) {
            $old = $this->get($update, "{$type}.old_chat_member", null);
            $new = $this->get($update, "{$type}.new_chat_member", null);

            $oldStatus = $this->get($old, 'status', null);
            $newStatus = $this->get($new, 'status', null);

            // Best-effort mapping
            if ($oldStatus !== $newStatus) {
                if (in_array($newStatus, ['left', 'kicked'], true)) return 'leave';
                if (in_array($newStatus, ['member', 'restricted'], true) && in_array($oldStatus, ['left', 'kicked'], true)) return 'join';
                if (in_array($newStatus, ['administrator', 'creator'], true)) return 'promoted';
                if (in_array($oldStatus, ['administrator', 'creator'], true) && in_array($newStatus, ['member', 'restricted'], true)) return 'demoted';
                if ($newStatus === 'restricted') return 'restricted';
                if ($oldStatus === 'restricted' && in_array($newStatus, ['member', 'administrator', 'creator'], true)) return 'unrestricted';
            }

            // If rights changed but status same
            $oldPerm = $this->get($old, 'permissions', null);
            $newPerm = $this->get($new, 'permissions', null);
            if ($oldPerm || $newPerm) return 'permission_changed';

            return 'member_changed';
        }

        if ($type === 'chat_join_request') return 'join_request';

        if ($type === 'callback_query') return 'callback';
        if (in_array($type, ['message', 'edited_message'], true)) return 'message';
        if (in_array($type, ['channel_post', 'edited_channel_post'], true)) return 'channel_post';

        return $type; // fallback
    }

    protected function extractRoom(mixed $update): RoomMeta
    {
        $chat = $this->extractChat($update);

        $chatId = $this->get($chat, 'id', null);
        $type = $this->get($chat, 'type', null) ?: 'unknown';
        $title = $this->get($chat, 'title', null);
        $username = $this->get($chat, 'username', null);

        // Forum topic/thread id (optional)
        $threadId = null;
        // message.message_thread_id (supergroup forums)
        foreach (['message', 'edited_message', 'channel_post', 'edited_channel_post'] as $k) {
            $threadId = $threadId ?? $this->get($update, "{$k}.message_thread_id", null);
        }

        return new RoomMeta(
            chatId: is_numeric($chatId) ? (int) $chatId : null,
            roomType: is_string($type) ? $type : 'unknown',
            title: is_string($title) ? $title : null,
            username: is_string($username) ? $username : null,
            threadId: is_numeric($threadId) ? (int) $threadId : null,
            raw: is_array($chat) ? $chat : [],
        );
    }

    protected function extractActor(mixed $update, RoomMeta $room): ActorMeta
    {
        // Actor inference based on update type
        $type = $this->detectUpdateType($update);

        // chat_member/my_chat_member has "from" as actor (who performed change)
        if (in_array($type, ['chat_member', 'my_chat_member'], true)) {
            $from = $this->get($update, "{$type}.from", null);
            $role = $this->inferRoleFromChatMember($this->get($update, "{$type}.new_chat_member", null));
            return ActorMeta::fromTelegramUser($from, $role);
        }

        // callback_query has "from"
        if ($type === 'callback_query') {
            $from = $this->get($update, "callback_query.from", null);
            return ActorMeta::fromTelegramUser($from, 'unknown');
        }

        // message/channel_post has message.from
        foreach (['message', 'edited_message', 'channel_post', 'edited_channel_post'] as $k) {
            $from = $this->get($update, "{$k}.from", null);
            if ($from) {
                // Channel posts may be "sender_chat" rather than "from"
                $role = 'unknown';
                return ActorMeta::fromTelegramUser($from, $role);
            }
            $senderChat = $this->get($update, "{$k}.sender_chat", null);
            if ($senderChat) {
                // if sender_chat exists, actor is a chat (channel/group) acting as sender.
                return ActorMeta::fromSenderChat($senderChat);
            }
        }

        return new ActorMeta(
            userId: null,
            username: null,
            name: null,
            type: 'unknown',
            role: 'unknown',
            raw: [],
        );
    }

    protected function extractTarget(mixed $update) : TargetMeta {
        // Actor inference based on update type
        $type = $this->detectUpdateType($update);
        $data = [];

        
        if (in_array($type, ['chat_member', 'my_chat_member'], true)) {
            $data[] = $this->get($update, "{$type}.new_chat_member.user", []);
        }
        
        if($type == "message") {
            $message = $update["message"];
            if (!empty($message['new_chat_members']) && is_array($message['new_chat_members'])) {
                $data = $this->get($update, "{$type}.new_chat_members", []);
            } elseif (isset($message["left_chat_member"])) {
                $data[] = $this->get($update, "{$type}.left_chat_member", []);
            } elseif(isset($message["pinned_message"]["from"])) {
                $data[] = $this->get($update, "{$type}.pinned_message.from", []);
            }
        }

        if ($type == "chat_join_request") {
            $data[] = $this->get($update, "{$type}.from", []);
        }

        $data = array_filter($data, fn($e) => !empty($e));

        if (count($data) == 0) {
            return new TargetMeta(
                id: null,
                is_bot: false,
                first_name: null,
                last_name: null,
                username: null,
                is_premium: false,
                raw: [],
                all: []
            );
        }

        $data = array_map(function($e) {
            return new TargetMeta(
                id: $this->get($e, "id", null),
                is_bot: $this->get($e, "is_bot", false),
                first_name: $this->get($e, "first_name", null),
                last_name: $this->get($e, "first_name", null),
                username: $this->get($e, "username", null),
                is_premium: $this->get($e, "is_premium", false),
                raw: $e,
                all: []
            );
        }, $data);

        $first = $data[0];

        return new TargetMeta(
            id: $first->id,
            is_bot: $first->is_bot,
            first_name: $first->first_name,
            last_name: $first->last_name,
            username: $first->username,
            is_premium: $first->is_premium,
            raw: $first->raw,
            all: $data
        );
    }

    protected function extractChange(mixed $update): ?ChangeMeta
    {
        $type = $this->detectUpdateType($update);

        if (in_array($type, ['chat_member', 'my_chat_member'], true)) {
            $old = $this->get($update, "{$type}.old_chat_member", null);
            $new = $this->get($update, "{$type}.new_chat_member", null);

            return new ChangeMeta(
                kind: 'chat_member',
                before: is_array($old) ? $old : (is_object($old) ? $this->toArray($old) : null),
                after: is_array($new) ? $new : (is_object($new) ? $this->toArray($new) : null),
            );
        }

        // Some permission changes might exist elsewhere; keep null unless we have a known old/new pair
        return null;
    }

    protected function extractChat(mixed $update): mixed
    {
        // chat_member/my_chat_member has .chat
        foreach (['chat_member', 'my_chat_member', 'chat_join_request'] as $k) {
            $chat = $this->get($update, "{$k}.chat", null);
            if ($chat) return $chat;
        }

        // callback_query.message.chat
        $chat = $this->get($update, "callback_query.message.chat", null);
        if ($chat) return $chat;

        // message-like
        foreach (['message', 'edited_message', 'channel_post', 'edited_channel_post'] as $k) {
            $chat = $this->get($update, "{$k}.chat", null);
            if ($chat) return $chat;
        }

        return null;
    }

    protected function inferRoleFromChatMember(mixed $chatMember): string
    {
        $status = $this->get($chatMember, 'status', null);
        if (!$status) return 'unknown';

        // Telegram statuses: creator, administrator, member, restricted, left, kicked
        return is_string($status) ? $status : 'unknown';
    }

    // --- Tiny safe access helpers (hybrid: works with arrays, SDK objects, stdClass) ---

    protected function has(mixed $data, string $path): bool
    {
        return $this->get($data, $path, '__MISSING__') !== '__MISSING__';
    }

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
                // irazasyed objects often implement ArrayAccess, or have magic properties.
                if ($cur instanceof \ArrayAccess && isset($cur[$p])) {
                    $cur = $cur[$p];
                    continue;
                }
                if (isset($cur->{$p})) {
                    $cur = $cur->{$p};
                    continue;
                }
                // sometimes getters exist
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

    protected function toArray(object $obj): array
    {
        if (method_exists($obj, 'toArray')) {
            return (array) $obj->toArray();
        }
        return json_decode(json_encode($obj), true) ?: [];
    }
}
