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
        $room = new self($chat->toArray());
        $room->threadId = $threadId;
        return $room;
    }
}
