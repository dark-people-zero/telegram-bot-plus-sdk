<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

final class RoomMeta
{
    public function __construct(
        public readonly ?int $chatId,
        public readonly string $roomType, // private|group|supergroup|channel|unknown
        public readonly ?string $title = null,
        public readonly ?string $username = null,
        public readonly ?int $threadId = null,
        public readonly array $raw = [],
    ) {}

    public function isGroupLike(): bool { return in_array($this->roomType, ['group', 'supergroup'], true); }
    public function isChannel(): bool { return $this->roomType === 'channel'; }
    public function isPrivate(): bool { return $this->roomType === 'private'; }
}
