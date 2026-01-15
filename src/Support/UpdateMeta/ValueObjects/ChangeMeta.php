<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

final class ChangeMeta
{
    public function __construct(
        public readonly string $kind, // e.g. chat_member
        public readonly ?array $before,
        public readonly ?array $after,
    ) {}

    public function hasBeforeAfter(): bool
    {
        return $this->before !== null || $this->after !== null;
    }
}
