<?php

namespace DarkPeople\TelegramBot\Reply;

use DarkPeople\TelegramBot\Support\BaseArraySerializable;

/**
 * Cache-safe pending state for "listen reply" feature.
 *
 * Two modes:
 * - inspector: owned by the SDK (Command Inspector) to complete missing required args/options step-by-step.
 * - custom:    owned by library users via setMessageListenReply(); the reply is routed back to a command's onReply().
 *
 * Inspector mode fields:
 * - baseInput: command name/path to re-dispatch (e.g. "make:model")
 * - args:      collected args so far
 * - options:   raw option tokens collected so far (e.g. ["--force", "--age=23"])
 * - next:      next item to ask: ['type' => 'arg'|'opt', 'name' => 'name'|'--age']
 */
final class PendingReply extends BaseArraySerializable
{
    public const MODE_INSPECTOR = 'inspector';
    public const MODE_CUSTOM = 'custom';

    /** @var string One of: inspector|custom */
    /** @var string Cache scope key: chat:{chatId}:user:{userId} */
    /** @var string|null Base command text (e.g. "make:model") */
    /** @var array<int, string> Collected args so far */
    /** @var array<string, mixed> Collected options so far */
    /**
     * @var array{type: string, name: string}|null
     * Example: ['type' => 'arg', 'name' => 'name']
     *          ['type' => 'opt', 'name' => 'force']
     */
    // ----- custom mode -----
    /** @var class-string|null Target command class for custom reply routing */
    public function __construct(
        public string $mode,
        public string $scope,
        public ?string $baseInput = null,
        public array $args = [],
        public array $options = [],
        public ?array $next = null,
        public ?string $commandClass = null,
        /** @var array<string, mixed> Free payload for user */
        public array $payload = [],
    ) {}
}
