<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

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

        $message = $renderer->render($result);

        if ($message) {
            $this->replyWithMessage(['text' => $message, 'parse_mode' => 'Markdown']);
        }

        return false;
    }
}
