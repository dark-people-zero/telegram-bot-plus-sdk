<?php

namespace DarkPeople\TelegramBot\Reply;

final class ReplyContext
{
    private static bool $active = false;

    /** @var string|null */
    private static ?string $text = null;

    /** @var PendingReply|null */
    private static ?PendingReply $pending = null;

    public static function set(string $text, PendingReply $pending): void
    {
        self::$active = true;
        self::$text = $text;
        self::$pending = $pending;
    }

    public static function active(): bool
    {
        return self::$active;
    }

    public static function text(): ?string
    {
        return self::$text;
    }

    public static function pending(): ?PendingReply
    {
        return self::$pending;
    }

    public static function clear(): void
    {
        self::$active = false;
        self::$text = null;
        self::$pending = null;
    }
}
