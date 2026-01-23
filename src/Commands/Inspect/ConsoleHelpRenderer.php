<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Reply\PendingReply;
use DarkPeople\TelegramBot\Reply\ReplyListenerStore;
use DarkPeople\TelegramBot\Support\ConsoleI18n;
use DarkPeople\TelegramBot\Support\TelegramContext;

/**
 * Render {@see ResolveResult} into Markdown output for the console interface.
 *
 * Responsibilities:
 * - convert resolver status into user-facing Markdown messages
 * - render help sections (root/group/command)
 * - render errors (not found, suggest, missing/invalid args/options, unauthorized)
 *
 * All human-readable texts must be provided by considered i18n dictionary
 * via {@see ConsoleI18n}.
 */
final class ConsoleHelpRenderer
{
    /**
     * @param ConsoleCommandRegistry $registry Command registry (for global options and root commands).
     */
    public function __construct(private ConsoleCommandRegistry $registry) {}

    /**
     * Render the given resolve result into Markdown.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    public function render(ResolveResult $r): ?string
    {
        $ctx = app(TelegramContext::class);
        
        return match ($r->status) {
            ResolveResult::OK => ConsoleI18n::get("ok"),

            ResolveResult::NOT_FOUND => ConsoleI18n::get('cmd.not_found', [
                'requested' => $r->requested ?? '',
            ]),

            ResolveResult::SUGGEST => $this->renderSuggest($r),

            ResolveResult::SHOW_ROOT_HELP => $this->renderRootHelp($r),
            ResolveResult::SHOW_GROUP_HELP => $this->renderGroupHelp($r),
            ResolveResult::SHOW_COMMAND_HELP => $this->renderLeafHelp($r),

            ResolveResult::MISSING_ARGUMENT => $this->renderMissingArgs($r, $ctx),
            ResolveResult::TOO_MANY_ARGUMENTS => $this->renderTooManyArgs($r),
            ResolveResult::INVALID_ARGUMENT => $this->renderInvalidArgs($r),

            ResolveResult::MISSING_OPTION => $this->renderMissingOptions($r, $ctx),
            ResolveResult::INVALID_OPTION => $this->renderInvalidOptions($r),
            ResolveResult::UNAUTHORIZE => $this->renderUnauthorize($r),

            default => null,
        };
    }

    /**
     * Build the description section for a command node.
     *
     * @param ResolveResult $r
     * @return array<int, string> Markdown lines.
     */
    private function getDesc(ResolveResult $r) : array
    {
        $lines = [];
        $lines[] = ConsoleI18n::get("help.description");
        if(!empty($r->node->description)) {
            $lines[] = "_{$r->node->description}_\n";
            return $lines;
        }

        return [];
    }

    /**
     * Build the usage section.
     *
     * If $text is provided, it will be used as the usage string.
     *
     * @param ResolveResult $r
     * @param string|null $text Custom usage text.
     * @return array<int, string> Markdown lines.
     */
    private function getUsage(ResolveResult $r, ?string $text = null) : array
    {
        $lines = [];
        $lines[] = ConsoleI18n::get("help.usage");
        if(!empty($text)) {
            $lines[] = "_{$text}_\n";
            return $lines;
        }

        $lines[] = "_command [arguments] [options]_\n";

        return $lines;
    }

    /**
     * Build the arguments section for the current node.
     *
     * @param ResolveResult $r
     * @return array<int, string> Markdown lines.
     */
    private function getArg(ResolveResult $r) : array
    {
        $lines = [];
        $lines[] = ConsoleI18n::get("help.args");
        if(!empty($r->node->arguments)) {
            foreach ($r->node->arguments as $args) {
                $lines[] = "• *`{$args->name}`*";
                if ($args->description) $lines[] = "_{$args->description}_\n";
            }
        }

        return count($lines) > 1 ? $lines : [];
    }

    /**
     * Build the options section (global + command-specific).
     *
     * @param ResolveResult $r
     * @return array<int, string> Markdown lines.
     */
    private function getOpt(ResolveResult $r) : array
    {
        $lines = [];
        $lines[] = ConsoleI18n::get("help.opts");
        $option = array_filter($r->node->options ?? [], fn(OptionSpec $e) => $e->short != '-h');
        $options = array_merge($this->registry->globalOptions(), $option);
        foreach ($options as $opt) {
            $flag = $opt->short ? "{$opt->short}, {$opt->long}" : $opt->long;
            $lines[] = "• `{$flag}`";
            if ($opt->description) $lines[] = "_{$opt->description}_\n";
        }

        return count($lines) > 1 ? $lines : [];
    }

    /**
     * Build the command list section.
     *
     * The list is filtered so only commands that pass authorization are shown.
     *
     * @param array<int, CommandNode> $nodes
     * @param CommandNode|null $parentNode Parent node context for group rendering.
     * @return array<int, string> Markdown lines.
     */
    private function getCommand(array $nodes, CommandNode $parentNode = null) : array
    {
        $nodes = array_filter($nodes, function(CommandNode $n) {
            return CommandAuthorization::authorizeCommand($n);
        });
        $lines = [];
        if(!$parentNode) {
            $lines[] = ConsoleI18n::get("help.root.title").":";
        } else {
            $nm = $this->formatCommandName($parentNode);
            $lines[] = ConsoleI18n::get("help.group.title", ["cmd" => $nm]);
        }
        foreach ($nodes as $cmd) {
            $text = $this->formatCommandName($cmd);
            // $text = empty($parent) ? $cmd->name : "{$parent} {$cmd->name}";
            $lines[] = "• `/$text`";
            if ($cmd->description) $lines[] = "_{$cmd->description}_\n";
        }
        
        return $lines;
    }

    /**
     * Format a command node into a full command path (space-separated).
     *
     * Example output:
     * "make seed"
     *
     * @param CommandNode|null $node
     * @return string
     */
    private function formatCommandName(?CommandNode $node): string
    {
        if (!$node) return '';
        $parts = [];
        $cur = $node;
        while ($cur) {
            $parts[] = $cur->name;
            $cur = $cur->parent;
        }
        return implode(' ', array_reverse($parts));
    }

    /**
     * Render "did you mean" suggestion output.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderSuggest(ResolveResult $r): string
    {
        $base = ConsoleI18n::get('cmd.not_found', ['requested' => "/".$r->requested ?? '']);
        if (empty($r->suggest)) return $base;

        $suggest = implode("\n", array_map(fn($s) => "• `/{$s}`", $r->suggest));
        $line2 = ConsoleI18n::get('cmd.did_you_mean', ['suggest' => "\n".$suggest]);

        return "$base\n\n$line2";
    }

    /**
     * Render root help (list all root commands + global options).
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderRootHelp(ResolveResult $r): string
    {
        $usage = $this->getUsage($r, "command [arguments] [options]");
        $option = $this->getOpt($r);
        $command = $this->getCommand($this->registry->nodes());
        $lines = array_merge($usage, $option, $command);
        
        return implode("\n", $lines);
    }

    /**
     * Render group help (list subcommands under the resolved group node).
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderGroupHelp(ResolveResult $r): string
    {
        $usage = $this->getUsage($r, "command [arguments] [options]");
        $option = $this->getOpt($r);
        $command = $this->getCommand($r->node->children, $r->node);
        $lines = array_merge($usage, $option, $command);
        
        return implode("\n", $lines);
    }

    /**
     * Render command (leaf) help including description, usage, args, and options.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderLeafHelp(ResolveResult $r): string
    {
        $desc = $this->getDesc($r);

        $options = array_filter($r->node->options, fn(OptionSpec $e) => $e->short != '-h');
        $cmd = $this->formatCommandName($r->node);
        $argsT = $r->node->arguments ? implode(' ', array_map(fn($a) => $a->required ? "<{$a->name}>" : "[{$a->name}]", $r->node->arguments)) : "";
        $optT = !empty($options) ? '[options]' : "";
        $usage = $this->getUsage($r, "/{$cmd} {$argsT} {$optT}");

        $args = $this->getArg($r);
        $option = $this->getOpt($r);
        $lines = array_merge($desc, $usage, $args, $option);

        return implode("\n", $lines);
    }

    /**
     * Render the "missing argument(s)" response.
     *
     * If interactive prompt mode is enabled, the renderer may store a pending reply state
     * and return a localized prompt asking for the next required argument value.
     *
     * @param ResolveResult   $r
     * @param TelegramContext $ctx
     * @return string|null
     */
    private function renderMissingArgs(ResolveResult $r, TelegramContext $ctx): ?string
    {
        $prompt = $this->makeReplyPrompt($r, $ctx);
        if (is_string($prompt) && trim($prompt) !== '') return $prompt;

        $cmd = $this->formatCommandName($r->node);
        $items = implode(', ', array_map(fn($x) => "`{$x}`", $r->missingArgs));

        return implode("\n\n", array_filter([
            ConsoleI18n::get('arg.missing', ['items' => $items]),
            ConsoleI18n::get('cmd.try_help', ['cmd' => "/$cmd"]),
        ]));
    }

    /**
     * Build and register an interactive "listen reply" prompt for Command Inspector.
     *
     * This method is invoked when the resolver returns:
     * - ResolveResult::MISSING_ARGUMENT
     * - ResolveResult::MISSING_OPTION
     *
     * Responsibilities:
     * 1) Determine the next required input (argument or option).
     * 2) Store a pending inspector state (cache-safe) scoped by chat and user.
     * 3) Generate a localized prompt message using ConsoleI18n::getPrompt().
     * 4) Append a cancel hint below the prompt.
     *
     * Inspector mode behavior:
     * - The pending state is stored with mode = "inspector".
     * - User replies are consumed to complete the missing argument/option.
     * - The command will be rebuilt and re-dispatched automatically.
     * - The command's onReply() method is NOT invoked in this mode.
     *
     * Prompt resolution rules:
     * - Prompt key:
     *   - Argument: argument name (e.g. "name", "age")
     *   - Option: option long name without leading dashes (e.g. "--age" => "age")
     * - Template priority:
     *   1) CommandNode::promptValue[key]
     *   2) Language dictionary "prompt.key"
     *   3) Language dictionary "prompt.default"
     * - Variables available in template:
     *   - {type}: "argument" or "option"
     *   - {text}: prompt key (argument/option name)
     *   - Additional variables provided via CommandNode::promptVarible
     *
     * State management:
     * - Pending state is scoped by "chat:{chatId}:user:{userId}".
     * - Default TTL is read from config "telegram.console.listen_reply_ttl".
     * - Any existing pending state is overwritten.
     *
     * Return value:
     * - Returns a formatted prompt string if interactive mode is activated.
     * - Returns null if:
     *   - The ResolveResult status is not supported.
     *   - Required context data is missing.
     *   - The pending state cannot be created.
     *
     * @param ResolveResult   $r   The resolve result indicating a missing argument or option.
     * @param TelegramContext $ctx The resolved Telegram context for the current update.
     * @return string|null The prompt message to be sent to the user, or null to fall back to default rendering.
     */
    private function makeReplyPrompt(ResolveResult $r, TelegramContext $ctx): ?string
    {
        if (!in_array($r->status, [ResolveResult::MISSING_ARGUMENT, ResolveResult::MISSING_OPTION], true)) {
            return null;
        }

        $scope = $this->makeScopeFromContext($ctx);
        if ($scope === null) return null;

        $ttl = (int) config('telegram.console.listen_reply_ttl', 120);
        if ($ttl < 1) $ttl = 120;

        $baseInput = is_string($ctx->commandName) && $ctx->commandName !== '' ? $ctx->commandName : null;
        if ($baseInput === null) return null;

        $next = null;
        $promptKey = null;
        $type = null;

        if ($r->status === ResolveResult::MISSING_ARGUMENT) {
            $name = $r->missingArgs[0] ?? null;
            if (!is_string($name) || $name === '') return null;

            $next = ['type' => 'arg', 'name' => $name];
            $promptKey = $name;
            $type = 'argument';
        }

        if ($r->status === ResolveResult::MISSING_OPTION) {
            $opt = $r->missingOptions[0] ?? null;
            if (!is_string($opt) || $opt === '') return null;

            $next = ['type' => 'opt', 'name' => $opt];
            $promptKey = ltrim($opt, '-');
            $type = 'option';
        }

        if ($next === null || $promptKey === null || $type === null) return null;

        $pending = new PendingReply(
            mode: PendingReply::MODE_INSPECTOR,
            scope: $scope,
            baseInput: $baseInput,
            args: $r->args ?? [],
            options: $r->options ?? [],
            next: $next,
        );

        /** @var ReplyListenerStore $store */
        $store = app(ReplyListenerStore::class);
        $store->put($scope, $pending, $ttl);

        $node = $r->node;

        $promptValue = is_object($node) ? ($node->promptValue ?? []) : [];
        $promptVarible = is_object($node) ? ($node->promptVarible ?? []) : [];

        $prompt = ConsoleI18n::getPrompt(
            key: $promptKey,
            vars: ['type' => $type, 'text' => $promptKey],
            promptValue: is_array($promptValue) ? $promptValue : [],
            promptVarible: is_array($promptVarible) ? $promptVarible : [],
        );

        $hint = ConsoleI18n::get('hint.cancel');

        return trim(implode("\n", array_filter([$prompt, $hint], fn($x) => is_string($x) && trim($x) !== '')));
    }

    /**
     * Render too many arguments error.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderTooManyArgs(ResolveResult $r): string
    {
        $cmd = $this->formatCommandName($r->node);
        return ConsoleI18n::get('arg.too_many') . "\n\n" . ConsoleI18n::get('cmd.try_help', ['cmd' => "/$cmd"]);
    }

    /**
     * Render invalid arguments error.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderInvalidArgs(ResolveResult $r): string
    {
        $cmd = $this->formatCommandName($r->node);
        $items = implode(', ', array_map(fn($x) => "`{$x}`", $r->invalidArgs));

        return ConsoleI18n::get('arg.invalid', ['items' => $items])
            . "\n\n"
            . ConsoleI18n::get('cmd.try_help', ['cmd' => "/$cmd"]);
    }

    /**
     * Render the "missing option(s)" response.
     *
     * If interactive prompt mode is enabled, the renderer may store a pending reply state
     * and return a localized prompt asking for the next required option value.
     *
     * @param ResolveResult   $r
     * @param TelegramContext $ctx
     * @return string
     */
    private function renderMissingOptions(ResolveResult $r, TelegramContext $ctx): string
    {
        $prompt = $this->makeReplyPrompt($r, $ctx);
        if (is_string($prompt) && trim($prompt) !== '') return $prompt;

        $cmd = $this->formatCommandName($r->node);
        $items = implode(', ', array_map(fn($x) => "`{$x}`", $r->missingOptions));

        return ConsoleI18n::get('opt.missing', ['items' => $items])
            . "\n\n"
            . ConsoleI18n::get('cmd.try_help', ['cmd' => "/$cmd"]);
    }

    /**
     * Render invalid options error.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderInvalidOptions(ResolveResult $r): string
    {
        $cmd = $this->formatCommandName($r->node);
        $items = implode(', ', array_map(fn($x) => "`{$x}`", $r->invalidOptions));

        return ConsoleI18n::get('opt.invalid', ['items' => $items])
            . "\n\n"
            . ConsoleI18n::get('cmd.try_help', ['cmd' => "/$cmd"]);
    }

    /**
     * Render unauthorized/forbidden output.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderUnauthorize(ResolveResult $r): string
    {
        return ConsoleI18n::get('unauthorize.title'). "\n\n" . ConsoleI18n::get('unauthorize.message');
    }

    private function makeScopeFromContext(TelegramContext $ctx): ?string
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

}
