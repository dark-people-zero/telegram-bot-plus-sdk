<?php

namespace DarkPeople\TelegramBot\Commands;

use DarkPeople\TelegramBot\Commands\Inspect\ConsoleCommandRegistry;
use DarkPeople\TelegramBot\Commands\Inspect\ConsoleHelpRenderer;
use DarkPeople\TelegramBot\Commands\Inspect\InteractsWithConsoleHelp;
use DarkPeople\TelegramBot\Commands\Inspect\ResolveResult;

use DarkPeople\TelegramBot\Commands\PlusCommand;

final class Help extends PlusCommand
{
    use InteractsWithConsoleHelp;
    protected string $name = 'help';
    protected string $description = 'List all available commands';

    /**
     * Handle menerima $arguments dari SDK,
     * tapi untuk routing fallback unknown command, kita pakai update asli:
     * $this->telegram->getWebhookUpdate()
     */
    public function handle(): void
    {
        $telegram = $this->telegram;
        $registry = ConsoleCommandRegistry::fromCommandBus($telegram);
        $renderer = new ConsoleHelpRenderer($registry);
        $result = new ResolveResult(ResolveResult::SHOW_ROOT_HELP);
        
        $this->replyWithMessage(['text' => $renderer->render($result)]);
        
    }

}
