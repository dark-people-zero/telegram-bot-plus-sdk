<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

/**
 * Resolve console command input into a {@see ResolveResult}.
 *
 * Responsibilities:
 * - tokenize input into command path segments and option tokens
 * - walk the command tree (root -> children) to find the target node
 * - handle help flags and "incomplete group" cases
 * - validate arguments and options (syntax-level)
 * - perform authorization check via command class authorize()
 *
 * This resolver does not render output. Rendering is handled separately.
 */
final class ConsoleCommandResolver
{
    /**
     * @param ConsoleCommandRegistry $registry  Command registry (command tree + global options).
     * @param array<int, string>     $helpFlags Option tokens that trigger help output.
     */
    public function __construct(
        private ConsoleCommandRegistry $registry,
        private array $helpFlags = ['--help', '-h']
    ) {}

    /**
     * Resolve raw console input into a {@see ResolveResult}.
     *
     * The input may contain:
     * - telegram-style prefixes ("/cmd", "cmd@bot")
     * - hierarchical commands using ":" (e.g. "make:seed")
     * - option tokens starting with "-" (e.g. "--help", "--name=dian")
     *
     * @param string $input Raw input string.
     * @return ResolveResult The resolution result (status + node + args/options context).
     */
    public function resolve(string $input): ResolveResult {
        $roots = $this->registry->nodes();
        $input = trim($input);
        if ($input === '') {
            return new ResolveResult(status: ResolveResult::SHOW_ROOT_HELP);
        }

        [$parts, $options] = $this->splitCommandAndOptions($input);
        $helpRequested = $this->hasHelpFlag($options);

        // no command tokens, only options
        if (count($parts) === 0) {
            return new ResolveResult(
                status: ResolveResult::SHOW_ROOT_HELP,
                options: $options,
            );
        }

        // 1. resolve root
        $rootName = strtolower($parts[0]);
        $root = $roots[$rootName] ?? null;

        if (!$root) {
            $sugest = DidYouMeanSuggester::suggest($rootName, array_keys($roots));
            return new ResolveResult(
                status: empty($sugest) ? ResolveResult::NOT_FOUND : ResolveResult::SUGGEST,
                requested: $parts[0],
                options: $options,
                suggest: $sugest
            );
        }

        $node = $root;
        $consumed = 1;

        // help on root itself: "make --help"
        if ($helpRequested && $consumed === count($parts) && !empty($node->children)) {
            return new ResolveResult(
                status: ResolveResult::SHOW_GROUP_HELP,
                node: $node,
                requested: $parts[0],
                options: $options,
                helpRequested: true,
            );
        }

        // 2) walk down children / detect leaf / split args
        while ($consumed < count($parts)) {
            $t = strtolower($parts[$consumed]);

            // if current node is a leaf (commandClass exists), stop consuming path:
            // remaining tokens become args
            if ($this->isLeaf($node)) break;

            // if no such child
            if (!isset($node->children[$t])) {
                // If node has NO children (dead-end group) but has arguments spec,
                // treat it as leaf-ish target and stop; remaining tokens become args.
                if (empty($node->children) && $this->expectsArgs($node)) break;

                // otherwise: it's truly not found under this group
                return new ResolveResult(
                    status: ResolveResult::NOT_FOUND,
                    node: $node,
                    requested: $parts[$consumed],
                    options: $options,
                );
            }

            // move
            $node = $node->children[$t];
            $consumed++;
        }

        // Remaining non-option tokens after path consumption are args
        $args = array_slice($parts, $consumed);

        if (!CommandAuthorization::authorizeCommand($node)) {
            return new ResolveResult(
                status: ResolveResult::UNAUTHORIZE,
                node: $node,
                requested: $node->name,
                args: $args,
                options: $options,
            );
        }

        // 3) If ended on a group (not leaf) and has children, it's incomplete -> show group help
        if (!$this->isLeaf($node) && !empty($node->children)) {
            // "make command" or "make"
            return new ResolveResult(
                status: ResolveResult::SHOW_GROUP_HELP,
                node: $node,
                requested: $node->name,
                args: $args,
                options: $options,
            );
        }
        
        $r = $this->validateArguments($node, $args, $options);
        if ($r) return $r;

        $r = $this->validateOptions($node, $options, $this->registry->globalOptions(), $args);
        if ($r) return $r;

        // 4) Leaf help requested: show leaf help (even if args wrong; help takes precedence)
        if ($helpRequested) {
            return new ResolveResult(
                status: ResolveResult::SHOW_COMMAND_HELP,
                node: $node,
                requested: $node->name,
                args: $args,
                options: $options,
                helpRequested: true,
            );
        }

        // OK
        return new ResolveResult(
            status: ResolveResult::OK,
            node: $node,
            requested: $node->name,
            args: $args,
            options: $options,
            helpRequested: $helpRequested,
        );
    }
    
    /**
     * Split input into:
     * - command tokens: non-option tokens (do not start with "-"), split further by ":"
     * - options: tokens that start with "-"
     *
     * Telegram-specific normalization:
     * - strips leading "/" prefix
     * - strips "@botname" suffix
     *
     * @param string $input Raw input.
     * @return array{0: array<int,string>, 1: array<int,string>} [commandTokens, options]
     */
    private function splitCommandAndOptions(string $input): array
    {
        $input = trim($input);
        if ($input === '') return [[], []];

        // 1) split awal pakai spasi
        $rawParts = preg_split('/\s+/', $input) ?: [];

        $commandTokens = [];
        $options = [];

        foreach ($rawParts as $part) {
            if ($part === '') continue;

            // 2) buang prefix "/" (telegram command)
            if ($part[0] === '/') {
                $part = substr($part, 1);
            }

            // 3) buang "@botname" (telegram)
            if (str_contains($part, '@')) {
                $part = explode('@', $part, 2)[0];
            }

            $part = strtolower($part);

            // 4) option (tidak ikut split ':')
            if (str_starts_with($part, '-')) {
                $options[] = $part;
                continue;
            }

            // 5) split command hierarchy via ":"
            foreach (explode(':', $part) as $seg) {
                if ($seg !== '') {
                    $commandTokens[] = $seg;
                }
            }
        }

        return [$commandTokens, $options];
    }
    
    /**
     * Check whether help is requested based on option tokens.
     *
     * @param array<int,string> $options
     * @return bool
     */
    private function hasHelpFlag(array $options): bool
    {
        foreach ($options as $o) {
            if (in_array($o, $this->helpFlags, true)) return true;
        }
        return false;
    }
    
    /**
     * Determine whether a node represents a leaf command.
     *
     * A leaf node is identified by having a non-null commandClass.
     *
     * @param CommandNode $node
     * @return bool
     */
    private function isLeaf(CommandNode $node): bool
    {
        return $node->commandClass !== null;
    }
    
    /**
     * Determine whether a node expects positional arguments.
     *
     * @param CommandNode $node
     * @return bool
     */
    private function expectsArgs(CommandNode $node): bool
    {
        return !empty($node->arguments);
    }

    /**
     * Validate positional arguments against {@see ArgumentSpec}.
     *
     * This validation checks:
     * - exact argument count expectation (based on compiled specs)
     * - per-argument regex validation (if a pattern is provided)
     *
     * On success, the resolved values are assigned to ArgumentSpec::$val.
     *
     * @param CommandNode        $node
     * @param array<int,string>  $args
     * @param array<int,string>  $options Raw option tokens for context in result.
     * @return ResolveResult|null Null if valid; otherwise a ResolveResult describing the error.
     */
    private function validateArguments(CommandNode $node, array $args, array $options): ?ResolveResult
    {
        /** @var array<int, ArgumentSpec> $specs */
        $specs = $node->arguments ?? [];

        $expected = count($specs);
        $argc = count($args);

        if ($argc < $expected) {
            $missing = array_slice(array_map(fn (ArgumentSpec $a) => $a->name, $specs), $argc);

            return new ResolveResult(
                status: ResolveResult::MISSING_ARGUMENT,
                node: $node,
                requested: $node->name,
                args: $args,
                options: $options,
                missingArgs: $missing,
            );
        }

        if ($argc > $expected) {
            return new ResolveResult(
                status: ResolveResult::TOO_MANY_ARGUMENTS,
                node: $node,
                requested: $node->name,
                args: $args,
                options: $options,
            );
        }

        // Map + validate pattern
        for ($i = 0; $i < $expected; $i++) {
            $val = (string) $args[$i];
            $specs[$i]->val = $val;

            $raw = $specs[$i]->pattern;
            if (is_string($raw) && trim($raw) !== '') {
                $regex = PatternParser::wrapPattern($raw, anchored: true);

                if ($regex === '' || @preg_match($regex, $val) !== 1) {
                    return new ResolveResult(
                        status: ResolveResult::INVALID_ARGUMENT,
                        node: $node,
                        requested: $node->name,
                        args: $args,
                        options: $options,
                        invalidArgs: [$specs[$i]->name],
                    );
                }
            }
        }

        $node->arguments = $specs;
        return null;
    }

    /**
     * Validate option tokens against {@see OptionSpec} (global + command-specific).
     *
     * This validation checks:
     * - whether a presented option exists (unknown options are ignored)
     * - required options presence
     * - value format when provided using "--opt=value" (if a pattern is defined)
     *
     * On success, command-specific options are assigned back to $node->options.
     *
     * @param CommandNode            $node
     * @param array<int,string>      $tokens        Raw option tokens (start with "-").
     * @param array<int,OptionSpec>  $globalOptions Global option specs.
     * @param array<int,string>      $args          Positional args (for result context).
     * @return ResolveResult|null Null if valid; otherwise a ResolveResult describing the error.
     */
    private function validateOptions(CommandNode $node, array $tokens, array $globalOptions, array $args): ?ResolveResult
    {
        /** @var array<int,OptionSpec> $specs */
        $specs = array_merge($globalOptions, $node->options ?? []);

        $byLong = [];
        $byShort = [];
        foreach ($specs as $s) {
            $byLong[strtolower($s->long)] = $s;
            if ($s->short) $byShort[strtolower($s->short)] = $s;
        }

        $present = [];

        foreach ($tokens as $tokRaw) {
            $tok = strtolower($tokRaw);

            $namePart = $tok;
            $valuePart = null;

            // --opt=value
            if (str_contains($tok, '=')) {
                [$namePart, $valuePart] = explode('=', $tok, 2);
                $namePart = strtolower($namePart);
            }

            $spec = $byLong[$namePart] ?? $byShort[$namePart] ?? null;
            if (!$spec) continue;

            $present[strtolower($spec->long)] = true;

            if ($valuePart !== null) {
                $spec->val = $valuePart;

                if (is_string($spec->pattern) && trim($spec->pattern) !== '') {
                    $regex = PatternParser::wrapPattern($spec->pattern, anchored: true);

                    if ($regex === '' || @preg_match($regex, $valuePart) !== 1) {
                        return new ResolveResult(
                            status: ResolveResult::INVALID_OPTION,
                            node: $node,
                            requested: $node->name,
                            args: $args,
                            options: $tokens,
                            invalidOptions: [$spec->long],
                        );
                    }
                }
            }
        }

        $missing = [];
        foreach ($specs as $s) {
            if (!$s->required) continue;

            if (!isset($present[strtolower($s->long)])) {
                $missing[] = $s->long;
            }
        }

        if (!empty($missing)) {
            return new ResolveResult(
                status: ResolveResult::MISSING_OPTION,
                node: $node,
                requested: $node->name,
                args: $args,
                options: $tokens,
                missingOptions: $missing,
            );
        }

        $node->options = array_values(array_filter($specs, fn (OptionSpec $s) => !$s->isGlobal));
        return null;
    }

}
