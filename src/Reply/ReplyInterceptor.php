<?php

namespace DarkPeople\TelegramBot\Reply;

use DarkPeople\TelegramBot\Support\TelegramContext;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

final class ReplyInterceptor
{
    public function __construct(
        private ReplyListenerStore $store
    ) {}

    /**
     * Pre-dispatch interceptor.
     *
     * Rules:
     * 1) If incoming text is a COMMAND:
     *    - if pending exists => cancel it
     *    - return false (let SDK handle normally)
     *
     * 2) If incoming text is PLAIN TEXT:
     *    - if no pending => return false (let SDK handle normally)
     *    - if pending mode = inspector => rebuild command text => dispatch via SDK => return true
     *    - if pending mode = custom    => set ReplyContext => dispatch target command => return true
     */
    public function intercept(TelegramContext $ctx): bool
    {
        $text = is_string($ctx->text) ? trim($ctx->text) : null;
        if ($text === null || $text === '') return false;

        $scope = $this->makeScope($ctx);
        if ($scope === null) return false;

        $pending = $this->store->get($scope);

        // If current message is a command, cancel pending (if any) and let SDK proceed.
        if ($ctx->isCommand) {
            if ($pending) {
                $this->store->forget($scope);
            }
            return false;
        }

        // Plain text:
        if (!$pending) return false;

        // consume once
        $this->store->forget($scope);

        if ($pending->mode === PendingReply::MODE_INSPECTOR) {
            $rebuilt = $this->buildInspectorCommand($pending, $text);
            if ($rebuilt === null) return false;

            $this->dispatchByText($ctx->telegram, $ctx->update(), $rebuilt);
            return true;
        }

        if ($pending->mode === PendingReply::MODE_CUSTOM) {
            if (!is_string($pending->commandClass) || $pending->commandClass === '') return false;

            ReplyContext::set($text, $pending);

            try {
                // For custom mode we dispatch to the stored command name (recommended).
                // If you store only class-string, you need a mapping layer (registry) to get the command name.
                $this->invokeCustomOnReply($ctx, $pending);
            } finally {
                ReplyContext::clear();
            }

            return true;
        }

        return false;
    }

    /**
     * Scope = chat_id + user_id.
     * Example: chat:123:user:999
     */
    private function makeScope(TelegramContext $ctx): ?string
    {
        $msg = $ctx->message;
        if (!is_object($msg)) return null;

        $chat = method_exists($msg, 'getChat') ? $msg->getChat() : null;
        $from = method_exists($msg, 'getFrom') ? $msg->getFrom() : null;

        $chatId = is_object($chat) && method_exists($chat, 'getId') ? $chat->getId() : null;
        $userId = is_object($from) && method_exists($from, 'getId') ? $from->getId() : null;

        if (!is_int($chatId) || !is_int($userId)) return null;

        return "chat:{$chatId}:user:{$userId}";
    }

    /**
     * Inspector rebuild strategy (step-by-step):
     * baseInput + collected args + replyText.
     *
     * For missing option step, you can extend this later by checking $pending->next['type'] === 'opt'
     * and appending --opt=value.
     */
    private function buildInspectorCommand(PendingReply $pending, string $replyText): ?string
    {
        if (!is_string($pending->baseInput) || trim($pending->baseInput) === '') return null;

        $base = trim($pending->baseInput);
        $reply = trim($replyText);

        $args = is_array($pending->args) ? $pending->args : [];
        $optTokens = is_array($pending->options) ? $pending->options : [];

        $next = $pending->next;
        $type = is_array($next) ? ($next['type'] ?? null) : null;
        $name = is_array($next) ? ($next['name'] ?? null) : null;

        if ($reply === '') return null;

        if ($type === 'opt') {
            // name is expected like "--age" (from missingOptions)
            $flag = is_string($name) && trim($name) !== '' ? trim($name) : null;
            if ($flag === null) return null;

            // normalize to long form with leading --
            if (!str_starts_with($flag, '--')) {
                $flag = '--' . ltrim($flag, '-');
            }

            // append asked option as --opt=value
            $optTokens[] = $flag . '=' . $reply;
        } else {
            // default: arg step
            $args[] = $reply;
        }

        $argsText = trim(implode(' ', array_filter($args, fn ($x) => is_string($x) && trim($x) !== '')));
        $optText  = trim(implode(' ', array_filter($optTokens, fn ($x) => is_string($x) && trim($x) !== '')));

        // result: "make:model jhon --force --age=23"
        return trim(implode(' ', array_filter([$base, $argsText, $optText])));
    }

    /**
     * Dispatch by a synthetic command text.
     * Your SDK requires Update to be passed to triggerCommand().
     */
    private function dispatchByText(Api $telegram, Update $update, string $text): void
    {
        $t = trim($text);
        if ($t === '') return;

        // normalize "/cmd ..." is fine; but triggerCommand usually wants command name.
        $cmd = $this->extractCommandName($t);
        if ($cmd === '') return;

        // IMPORTANT: pass Update as required by your integration
        $telegram->triggerCommand($cmd, $update);
    }

    private function invokeCustomOnReply(TelegramContext $ctx, PendingReply $pending): void
    {
        $commandClass = $pending->commandClass;

        if (!is_string($commandClass) || $commandClass === '') return;

        $payload = is_array($pending->payload) ? $pending->payload : [];

        $commandClass::onReply($ctx, $payload);
    }


    private function extractCommandName(string $text): string
    {
        // Accept both "make:model jhon" and "/make:model jhon"
        $t = ltrim($text);
        if ($t === '') return '';

        if ($t[0] === '/') $t = substr($t, 1);

        $t = explode(' ', $t, 2)[0] ?? '';
        $t = explode('@', $t, 2)[0] ?? $t;

        return strtolower(trim($t));
    }
}
