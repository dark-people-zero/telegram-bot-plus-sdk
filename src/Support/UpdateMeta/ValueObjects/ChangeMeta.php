<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

use Telegram\Bot\Objects\ChatMember;

final class ChangeMeta
{
    public function __construct(
        public ?string $kind = "unknown",
        public ?ChatMember $before = null,
        public ?ChatMember $after = null,
    ) {}

    public function hasBeforeAfter(): bool
    {
        return $this->before !== null || $this->after !== null;
    }
}
