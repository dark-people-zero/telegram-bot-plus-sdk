<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta;

use DarkPeople\TelegramBot\Support\UpdateMeta\Permissions\PermissionResolver;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ActorMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\RoomMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ChangeMeta;
use DarkPeople\TelegramBot\Support\TelegramContext;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\TargetMeta;
use Telegram\Bot\Objects\Chat;

final class TelegramUpdateAnalyzer
{
    protected TelegramContext $ctx;
    public function __construct(
        protected PermissionResolver $permissionResolver,
    ) {}

    public function analyze(TelegramContext $ctx): TelegramUpdateMeta
    {
        $this->ctx = $ctx;

        $update = $ctx->update->all();

        $action = $this->detectAction();
        $target = $this->extractTarget();

        $room = $this->extractRoom();
        $actor = $this->extractActor();

        $change = $this->extractChange();

        $permissions = $this->permissionResolver->resolve($ctx, $actor, $room, $change);

        return new TelegramUpdateMeta(
            action: $action,
            actor: $actor,
            target: $target,
            room: $room,
            change: $change,
            permissions: $permissions,
            raw: $update,
        );
    }

    /**
     * Your internal "action" taxonomy.
     */
    protected function detectAction(): string
    {
        $type = $this->ctx->eventName;

        // Membership change updates are best for join/leave/promote/restrict, etc.
        if (in_array($type, ['chat_member', 'my_chat_member'], true)) {
            $chatMember = $type === 'my_chat_member'
                ? $this->ctx->update->myChatMember
                : $this->ctx->update->chatMember;

            if (!$chatMember) return 'member_changed';

            $old = $chatMember->oldChatMember;
            $new = $chatMember->newChatMember;

            $oldStatus = $old->status;
            $newStatus = $new->status;

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
            $oldPerm = $old->filter(fn($v, $k) => str_starts_with($k, "can_"))->sortKeys()->all();
            $newPerm = $new->filter(fn($v, $k) => str_starts_with($k, "can_"))->sortKeys()->all();
            if ($oldPerm !== $newPerm) return 'permission_changed';

            return 'member_changed';
        }

        if ($type === 'chat_join_request') return 'join_request';

        if ($type === 'callback_query') return 'callback';
        if (in_array($type, ['message', 'edited_message'], true)) return 'message';
        if (in_array($type, ['channel_post', 'edited_channel_post'], true)) return 'channel_post';

        return $type; // fallback
    }

    protected function extractTarget() : TargetMeta
    {
        $update = $this->ctx->update;
        if ($update->chatMember) {
            $newChatMember = $update->chatMember->newChatMember;
            if($newChatMember && $newChatMember->user) return TargetMeta::fromUser([$newChatMember->user], $newChatMember->status);
        }

        if ($update->myChatMember) {
            $newChatMember = $update->myChatMember->newChatMember;
            if($newChatMember && $newChatMember->user) return TargetMeta::fromUser([$newChatMember->user], $newChatMember->status);
        }

        if ($update->chatJoinRequest && $update->chatJoinRequest->from) return TargetMeta::fromUser([$update->chatJoinRequest->from]);

        if ($update->message) {
            $message = $update->message;
            if ($message->newChatMembers) return TargetMeta::fromUser($message->getNewChatMembers()->all());
            if ($message->leftChatMember) return TargetMeta::fromUser([$message->leftChatMember]);
            if ($message->pinnedMessage && $message->pinnedMessage->from) return TargetMeta::fromUser([$message->pinnedMessage->from]);
        }


        return new TargetMeta([]);
    }

    protected function extractRoom(): RoomMeta
    {
        $chat = $this->ctx->update->getChat();
        $message = $this->ctx->update->getMessage();
        $threadId = $message->get("message_thread_id", null);

        if ($chat instanceof Chat) {
            return RoomMeta::fromChat($chat, $threadId);
        }

        return new RoomMeta([]);
    }

    protected function extractActor(): ActorMeta
    {

        $update = $this->ctx->update;

        if ($update->chatMember && $update->chatMember->from) {
            $member = $update->chatMember;
            return ActorMeta::fromUser($member->from, $member->newChatMember->status);
        }

        if ($update->myChatMember && $update->myChatMember->from) {
            $member = $update->myChatMember;
            return ActorMeta::fromUser($member->from, $member->newChatMember->status);
        }

        if ($update->chatJoinRequest && $update->chatJoinRequest->from) {
            $member = $update->chatJoinRequest;
            return ActorMeta::fromUser($member->from);
        }

        if ($update->callbackQuery && $update->callbackQuery->from) {
            $member = $update->callbackQuery;
            return ActorMeta::fromUser($member->from);
        }

        if ($update->pollAnswer && $update->pollAnswer->user) {
            $member = $update->pollAnswer;
            return ActorMeta::fromUser($member->user);
        }

        $message = $update->getMessage();
        if ($message->has("from")) return ActorMeta::fromUser($message->get("from"));
        if ($message->has("sender_chat")) return ActorMeta::fromSender($message->get("sender_chat"));

        return new ActorMeta([]);
    }

    protected function extractChange(): ?ChangeMeta
    {
        $update = $this->ctx->update;
        if($update->chatMember) {
            return new ChangeMeta(
                kind: 'chat_member',
                before: $update->chatMember->oldChatMember,
                after: $update->chatMember->newChatMember,
            );
        }

        if($update->myChatMember) {
            return new ChangeMeta(
                kind: 'chat_member',
                before: $update->myChatMember->oldChatMember,
                after: $update->myChatMember->newChatMember,
            );
        }

        // Some permission changes might exist elsewhere; keep null unless we have a known old/new pair
        return new ChangeMeta;
    }

}
