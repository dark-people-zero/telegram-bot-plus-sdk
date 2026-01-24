<?php

namespace DarkPeople\TelegramBot\Reply;

use DarkPeople\TelegramBot\Reply\PendingReply;
use DarkPeople\TelegramBot\Reply\ReplyListenerStore;
use DarkPeople\TelegramBot\Support\ConsoleI18n;
use DarkPeople\TelegramBot\Support\TelegramContext;

trait InteractsWithListenReply
{
    /**
     * Set pending reply state (custom mode).
     *
     * @param array<string, mixed> $payload Free payload for user (will be sent to onReply)
     * @param string|null $prompt Optional prompt text (already localized if needed)
     * @param int|null $ttlSeconds Override TTL (seconds)
     */
    protected function setMessageListenReply(
        array $payload = [],
        ?string $prompt = null,
        ?int $ttlSeconds = null
    ): void {
        /** @var TelegramContext $ctx */
        $ctx = app(TelegramContext::class);

        $scope = $this->makeScopeFromContext($ctx);
        if ($scope === null) return;

        $ttl = is_int($ttlSeconds) ? $ttlSeconds : (int) config('telegram.console.listen_reply_ttl', 120);
        if ($ttl < 1) $ttl = 120;

        $pending = new PendingReply(
            mode: PendingReply::MODE_CUSTOM,
            scope: $scope,
            commandClass: static::class,
            payload: $payload,
        );

        /** @var ReplyListenerStore $store */
        $store = app(ReplyListenerStore::class);
        $store->put($scope, $pending, $ttl);

        if (is_string($prompt) && trim($prompt) !== '' && method_exists($this, 'replyWithMessage')) {
            $text = ConsoleI18n::get($prompt);

            if (is_string($text) && trim($text) !== '') {
                $this->replyWithMessage(['text' => $text]);
            }
        }
    }


    private function makeScopeFromContext(TelegramContext $ctx): ?string
    {
        $msg = $ctx->message;
        if (!is_object($msg)) return null;

        $chat = $msg->getChat();
        $from = $msg->getFrom();

        $chatId = $chat->getId();
        $userId = $from->getId();

        if (!is_int($chatId) || !is_int($userId)) return null;

        return "chat:{$chatId}:user:{$userId}";
    }
}
