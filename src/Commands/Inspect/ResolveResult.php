<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

/**
 * Represents the result of resolving a console command input.
 *
 * ResolveResult is the single source of truth for command execution flow.
 * Callers must rely on {@see shouldStop()} to determine whether execution
 * should proceed.
 *
 * A single resolve produces exactly one status.
 * Rendering and messaging are handled separately based on this status.
 */
final class ResolveResult
{
    /** Command is valid and ready to be executed. */
    public const OK = 'OK';

    /** Command was not found. */
    public const NOT_FOUND = 'NOT_FOUND';

    /** Command was not found, but suggestions are available. */
    public const SUGGEST = 'SUGGEST';

    /** Input stopped at root level; show root help. */
    public const SHOW_ROOT_HELP = 'SHOW_ROOT_HELP';

    /** Input stopped at a group node; show group help. */
    public const SHOW_GROUP_HELP = 'SHOW_GROUP_HELP';

    /** Show help for a leaf command. */
    public const SHOW_COMMAND_HELP = 'SHOW_COMMAND_HELP';

    /** Required option is missing. */
    public const MISSING_OPTION = 'MISSING_OPTION';

    /** Option value is invalid. */
    public const INVALID_OPTION = 'INVALID_OPTION';

    /** Required argument is missing. */
    public const MISSING_ARGUMENT = 'MISSING_ARGUMENT';

    /** Too many arguments were provided. */
    public const TOO_MANY_ARGUMENTS = 'TOO_MANY_ARGUMENTS';

    /** Argument value is invalid. */
    public const INVALID_ARGUMENT = 'INVALID_ARGUMENT';

    /** Command execution is not authorized. */
    public const UNAUTHORIZE = 'UNAUTHORIZE';

    /**
     * @param string                 $status          One of the ResolveResult::STATUS constants.
     * @param CommandNode|null       $node            Resolved command node (if any).
     * @param string|null            $requested       The requested token or command name.
     * @param array<int, string>     $args            Positional arguments.
     * @param array<int, string>     $options         Raw option tokens.
     * @param array<int, string>     $suggest         Suggested command names (for SUGGEST).
     * @param array<int, string>     $missingArgs     Missing argument names.
     * @param array<int, string>     $invalidArgs     Invalid argument names.
     * @param array<int, string>     $missingOptions  Missing option names.
     * @param array<int, string>     $invalidOptions  Invalid option names.
     * @param bool                   $helpRequested   Whether help was explicitly requested.
     */
    public function __construct(
        public string $status,

        public ?CommandNode $node = null,
        public ?string $requested = null,

        public array $args = [],
        public array $options = [],

        public array $suggest = [],
        public array $missingArgs = [],
        public array $invalidArgs = [],
        public array $missingOptions = [],
        public array $invalidOptions = [],

        public bool $helpRequested = false,
    ) {}

    /**
     * Determine whether command execution should stop.
     *
     * Execution is allowed only when the status is {@see self::OK}.
     *
     * @return bool True if execution should stop; false otherwise.
     */
    public function shouldStop(): bool
    {
        return $this->status !== self::OK;
    }
}
