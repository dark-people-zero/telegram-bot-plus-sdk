<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Authoring;

final class AuthoringMiddlewareRule
{
    /**
     * @param class-string $class
     * @param string       $event     event key / group / 'command'
     * @param string[]|null $forBot   null = semua bot, array = whitelist bot
     * @param string[]|null $commands null = bukan command-scope, ['*'] atau list command
     * @param non-empty-string $sourceType contoh: 'config:global', 'config:bot'
     */
    public function __construct(
        public readonly string $class,
        public readonly string $event,
        public readonly ?array $forBot = null,
        public readonly ?array $commands = null,
        public readonly string $sourceType = 'config',
    ) {}
}
