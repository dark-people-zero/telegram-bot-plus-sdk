<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Support\TelegramContext;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

/**
 * Intercept Telegram text updates to provide console-like help and validation output.
 *
 * This trait:
 * - builds a console command registry from the CommandBus
 * - resolves incoming text into a {@see ResolveResult}
 * - renders help/error output using {@see ConsoleHelpRenderer} when execution should stop
 * - when execution is allowed, optionally resolves options and forwards them to the host class
 *
 * Host class optional methods:
 * - setOptions(array $options): void
 * - replyWithMessage(array $payload): mixed
 */
trait InteractsWithConsoleHelp
{
    /**
     * Determine whether console help interception is enabled.
     *
     * @return bool True to enable interception; false to bypass.
     */
    protected function shouldInterceptConsoleHelp(): bool
    {
        return true;
    }

    /**
     * Intercept before the command handler is executed.
     *
     * Returns:
     * - true  : allow execution to proceed (result should not stop)
     * - false : stop execution and (optionally) reply with rendered help/error message
     *
     * @param Api $telegram Telegram SDK API instance.
     * @param Update $update Incoming Telegram update.
     * @param array $entity Telegram entity context (handler-specific).
     * @return bool
     */
    protected function beforeHandleIntercept(Api $telegram, Update $update, array $entity): bool
    {
        $text = $update->getMessage()->get("text", null);
        if (empty($text)) return false;

        $registry = ConsoleCommandRegistry::fromCommandBus($telegram);
        $resolver = new ConsoleCommandResolver($registry);
        $result = $resolver->resolve($text);

        $renderer = new ConsoleHelpRenderer($registry);

        if(!$result->shouldStop()) {
            $optionInput = $result->options;
            $specOptions = $result->node?->options ?? null;
            if($specOptions !== null && method_exists($this, 'setOptions')) {
                $this->setOptions(PatternParser::resolveOptions($optionInput, $specOptions));
            }
            return true;
        }

        /** @var TelegramContext $ctx */
        $ctx = app(TelegramContext::class);

        // Inspector interactive for missing arg/opt
        if (in_array($result->status, [ResolveResult::MISSING_ARGUMENT, ResolveResult::MISSING_OPTION], true)) {

            $scope = $this->makeReplyScopeFromContext($ctx);
            if ($scope !== null) {
                $ttl = (int) config('telegram.console.listen_reply_ttl', 120);
                if ($ttl < 1) $ttl = 120;

                // base command name should be colon-joined: "make:model"
                $baseInput = $result->node ? $this->colonCommandNameFromNode($result->node) : null;

                // pick next step
                $next = null;
                $promptKey = null;
                $promptVars = [];

                if ($result->status === ResolveResult::MISSING_ARGUMENT) {
                    $missing = $result->missingArgs[0] ?? null;
                    if (is_string($missing) && $missing !== '') {
                        $next = ['type' => 'arg', 'name' => $missing];
                        $promptKey = 'listen.arg.ask';
                        $promptVars = ['name' => $missing];
                    }
                }

                if ($result->status === ResolveResult::MISSING_OPTION) {
                    $missing = $result->missingOptions[0] ?? null;
                    if (is_string($missing) && $missing !== '') {
                        // missingOptions contains long flag like "--age"
                        $next = ['type' => 'opt', 'name' => $missing];
                        $promptKey = 'listen.opt.ask';
                        $promptVars = ['name' => $missing];
                    }
                }

                if ($baseInput !== null && $next !== null) {
                    $pending = new PendingReply(
                        mode: PendingReply::MODE_INSPECTOR,
                        scope: $scope,
                        baseInput: $baseInput,
                        args: $result->args ?? [],
                        optionTokens: $result->options ?? [],
                        next: $next,
                    );

                    /** @var ReplyListenerStore $store */
                    $store = app(ReplyListenerStore::class);
                    $store->put($scope, $pending, $ttl);

                    // send prompt (i18n)
                    $prompt = $promptKey ? ConsoleI18n::get($promptKey, $promptVars) : '';
                    $hint   = ConsoleI18n::get('listen.cancel.hint');

                    $msg = trim(implode("\n", array_filter([$prompt, $hint], fn ($x) => is_string($x) && trim($x) !== '')));

                    if ($msg !== '' && method_exists($this, 'replyWithMessage')) {
                        $this->replyWithMessage(['text' => $msg]);
                    }

                    return false; // stop, waiting for reply
                }
            }
        }

        $message = $renderer->render($result);

        if ($message) {
            if (method_exists($this, 'replyWithMessage')) {
                $this->replyWithMessage(['text' => $message]);
            }
        }

        return false;
    }
}
