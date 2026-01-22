<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Support\ConsoleI18n;

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
    public function render(ResolveResult $r): string
    {
        return match ($r->status) {
            ResolveResult::OK => ConsoleI18n::get("ok"),

            ResolveResult::NOT_FOUND => ConsoleI18n::get('cmd.not_found', [
                'requested' => $r->requested ?? '',
            ]),

            ResolveResult::SUGGEST => $this->renderSuggest($r),

            ResolveResult::SHOW_ROOT_HELP => $this->renderRootHelp($r),
            ResolveResult::SHOW_GROUP_HELP => $this->renderGroupHelp($r),
            ResolveResult::SHOW_COMMAND_HELP => $this->renderLeafHelp($r),

            ResolveResult::MISSING_ARGUMENT => $this->renderMissingArgs($r),
            ResolveResult::TOO_MANY_ARGUMENTS => $this->renderTooManyArgs($r),
            ResolveResult::INVALID_ARGUMENT => $this->renderInvalidArgs($r),

            ResolveResult::MISSING_OPTION => $this->renderMissingOptions($r),
            ResolveResult::INVALID_OPTION => $this->renderInvalidOptions($r),
            ResolveResult::UNAUTHORIZE => $this->renderUnauthorize($r),

            default => "",
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
        $options = array_merge($this->registry->globalOptions(), $r->node->options ?? []);
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
            $nm = $parent = $this->formatCommandName($parentNode);
            $lines[] = ConsoleI18n::get("help.group.title", ["cmd" => $nm]);
        }
        foreach ($nodes as $cmd) {
            $parent = $this->formatCommandName($cmd);
            $text = empty($parent) ? $cmd->name : "{$parent} {$cmd->name}";
            $lines[] = "• `$text`";
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
        $base = ConsoleI18n::get('cmd.not_found', ['requested' => $r->requested ?? '']);
        if (empty($r->suggest)) return $base;

        $suggest = implode(', ', array_map(fn($s) => "`{$s}`", $r->suggest));
        $line2 = ConsoleI18n::get('cmd.did_you_mean', ['suggest' => $suggest]);

        return "$base\n$line2";
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
        
        return implode("\n\n", $lines);
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
        
        return implode("\n\n", $lines);
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

        $cmd = $this->formatCommandName($r->node);
        $argsT = $r->node->arguments ? implode(' ', array_map(fn($a) => $a->required ? "<{$a->name}>" : "[{$a->name}]", $r->node->arguments)) : "";
        $optT = !empty($r->node->options) ? '[options]' : "";
        $usage = $this->getUsage($r, "{$cmd} {$argsT} {$optT}");

        $args = $this->getArg($r);
        $option = $this->getOpt($r);
        $lines = array_merge($desc, $usage, $args, $option);
        
        return implode("\n\n", $lines);
    }

    /**
     * Render missing arguments error.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderMissingArgs(ResolveResult $r): string
    {
        $cmd = $this->formatCommandName($r->node);
        $items = implode(', ', array_map(fn($x) => "`{$x}`", $r->missingArgs));

        return implode("\n", array_filter([
            ConsoleI18n::get('arg.missing', ['items' => $items]),
            ConsoleI18n::get('cmd.try_help', ['cmd' => $cmd]),
        ]));
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
        return ConsoleI18n::get('arg.too_many') . "\n" . ConsoleI18n::get('cmd.try_help', ['cmd' => $cmd]);
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
            . "\n"
            . ConsoleI18n::get('cmd.try_help', ['cmd' => $cmd]);
    }

    /**
     * Render missing required options error.
     *
     * @param ResolveResult $r
     * @return string Markdown output.
     */
    private function renderMissingOptions(ResolveResult $r): string
    {
        $cmd = $this->formatCommandName($r->node);
        $items = implode(', ', array_map(fn($x) => "`{$x}`", $r->missingOptions));

        return ConsoleI18n::get('opt.missing', ['items' => $items])
            . "\n"
            . ConsoleI18n::get('cmd.try_help', ['cmd' => $cmd]);
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
            . "\n"
            . ConsoleI18n::get('cmd.try_help', ['cmd' => $cmd]);
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

}
