<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

use Telegram\Bot\Objects\ChatMember;

final class ChangeMeta
{
    public function __construct(
        public readonly ?string $kind = "unknown", // e.g. chat_member|unknown
        public readonly ?ChatMember $before = null,
        public readonly ?ChatMember $after = null,
    ) {}

    public function hasBeforeAfter(): bool
    {
        return $this->before !== null || $this->after !== null;
    }
}
