<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

final class TargetMeta
{
    public function __construct(
        public readonly ?int $id,
        public readonly bool $is_bot,
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly array $raw = [],
        public readonly array $all = [],
    ) {}

    public function first(int $index = 0) {
        return $this->all[$index];
    }

}
