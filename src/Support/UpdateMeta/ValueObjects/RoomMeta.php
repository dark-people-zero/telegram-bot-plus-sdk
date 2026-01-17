<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

use Telegram\Bot\Objects\Chat;

final class RoomMeta extends Chat
{
    public readonly ?int $threadId;

    public function isGroupLike(): bool { return in_array($this->type, ['group', 'supergroup'], true); }
    public function isChannel(): bool { return $this->type === 'channel'; }
    public function isPrivate(): bool { return $this->type === 'private'; }

    public static function fromChat(Chat $chat, ?int $threadId = null): self
    {
        $room = new self();
        $room->threadId = $threadId;
        $room->id = $chat->id;
        $room->type = $chat->type;
        $room->title = $chat->title;
        $room->username = $chat->username;
        $room->firstName = $chat->firstName;
        $room->lastName = $chat->lastName;
        $room->photo = $chat->photo;
        $room->bio = $chat->bio;
        $room->hasPrivateForwards = $chat->hasPrivateForwards;
        $room->description = $chat->description;
        $room->inviteLink = $chat->inviteLink;
        $room->pinnedMessage = $chat->pinnedMessage;
        $room->permissions = $chat->permissions;
        $room->slowModeDelay = $chat->slowModeDelay;
        $room->hasProtectedContent = $chat->hasProtectedContent;
        $room->stickerSetName = $chat->stickerSetName;
        $room->canSetStickerSet = $chat->canSetStickerSet;
        $room->linkedChatId = $chat->linkedChatId;
        $room->location = $chat->location;

        return $room;
    }
}
