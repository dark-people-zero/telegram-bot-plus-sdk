<?php

namespace DarkPeople\TelegramBot\Commands;

use DarkPeople\TelegramBot\Commands\Concerns\HasPlusCommandMeta;
use DarkPeople\TelegramBot\Commands\Contracts\PlusCommandMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\TelegramUpdateMeta;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Api;

abstract class PlusCommand extends Command implements PlusCommandMeta
{
    use HasPlusCommandMeta;

    /**
     * The argument definitions for this Telegram command.
     *
     * This property describes the positional arguments accepted by the command,
     * including their order, requirement, and validation pattern.
     *
     * The structure is typically produced by argument pattern serialization
     * and mapped into ArgumentSpec objects during compilation.
     *
     * @var array<int, mixed>
     */
    protected array $argumentDescription = [];

    /**
     * The raw option pattern definition for this Telegram command.
     *
     * This pattern is used to define the available command options before being
     * compiled into OptionSpec instances.
     *
     * The pattern itself does not perform validation; it is later used for
     * option extraction and filtering during the consume phase.
     *
     * @var string
     */
    protected string $patternOption = '';

    /**
     * The option descriptions for this Telegram command.
     *
     * This property provides human-readable descriptions for each option,
     * typically used by the console renderer when displaying help messages.
     *
     * @var array<string, mixed>
     */
    protected array $optionDescription = [];

    /**
     * The child command nodes of this node.
     *
     * Children represent sub-commands or grouped command hierarchies
     * and are traversed recursively during command resolution,
     * authorization checks, and help rendering.
     *
     * @var array<int, self>
     */
    protected array $children = [];

    /**
     * Resolved option values for the current command execution.
     *
     * This property is populated after option consumption and filtering
     * has completed successfully.
     *
     * The array is keyed by option name (long name without leading dashes).
     *
     * Example:
     * [
     *   'name'  => 'dian',
     *   'force' => true,
     * ]
     *
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * Prompt template overrides for interactive "listen reply" flow.
     *
     * Used by Command Inspector to ask missing values step-by-step.
     *
     * Shape:
     * [
     *   'name' => "Mau beri nama apa?",
     *   'age'  => "Umur berapa?",
     * ]
     *
     * Key rules:
     * - For arguments: use argument name (e.g. "name")
     * - For options:   use option name without dashes (e.g. "--age" => "age")
     *
     * @var array<string, string>
     */
    protected array $promptValue = [];

    /**
     * Default prompt variables for interactive prompt rendering.
     *
     * Useful for providing extra variables for ConsoleI18n::getPrompt(), for example:
     * [
     *   'default' => ['type' => 'argument'],
     *   'age'     => ['type' => 'option'],
     * ]
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $promptVarible = [];

    /**
     * Determine whether the current update is authorized to execute this command.
     *
     * This method acts as a lightweight authorization gate and must be a pure,
     * deterministic function.
     *
     * Implementations should not perform side effects or expensive operations
     * such as database queries, network calls, or file I/O.
     *
     * Returning true allows the command execution to proceed.
     * Returning false will stop the execution and trigger an unauthorized response.
     *
     * @param TelegramUpdateMeta $meta The incoming Telegram update metadata.
     * @return bool True if the command is authorized; false otherwise.
     */
    public static function authorize(TelegramUpdateMeta $meta): bool
    {
        return true;
    }

    public static function onReply(TelegramUpdateMeta $ctx, array $payload): void
    {
        // Default no-op implementation.
    }
    
    /**
     * Get prompt template overrides for interactive "listen reply" flow.
     *
     * @return array<string, string>
     */
    public function getPromptValue() : array {
        return $this->promptValue ?? [];
    }

    /**
     * Get default prompt variables for interactive "listen reply" flow.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPromptVarible() : array {
        return $this->promptVarible ?? [];
    }

    /**
     * Get argument descriptions for this command.
     *
     * The returned array maps argument names to their human-readable descriptions.
     *
     * @return array<string, string>
     */
    public function getArgDesc() : array
    {
        return $this->argumentDescription ?? [];
    }

    /**
     * Get the raw option pattern definition.
     *
     * This pattern uses the same format as argument patterns
     * and will be compiled into OptionSpec instances.
     *
     * @return string
     */
    public function getPatternOption() : string
    {
        return $this->patternOption ?? '';
    }

    /**
     * Get option descriptions for this command.
     *
     * The returned array maps option names to their human-readable descriptions.
     *
     * @return array<string, string>
     */
    public function getOptionDesc() : array
    {
        return $this->optionDescription ?? [];
    }

    /**
     * Get child command classes.
     *
     * Each value represents a sub-command class.
     *
     * @return array<int, class-string>
     */
    public function getChildren() : array
    {
        return $this->children ?? [];
    }

    /**
     * Get resolved option values for this command execution.
     *
     * @return array<string, mixed>
     */
    public function getOptions() : array
    {
        return $this->options ?? [];
    }

    /**
     * Set resolved option values for this command execution.
     *
     * This method is typically called after option consumption
     * has completed successfully.
     *
     * @param array<string, mixed> $item
     * @return void
     */
    public function setOptions(array $item) {
        $this->options = $item;
    }

    /**
     * Get a single resolved option value by key.
     *
     * @param string $key Option name (long name without leading dashes).
     * @return string|null
     */
    public function getOption(string $key): string|null
    {
        return (string) $this->options[$key] ?? null;
    }

    /**
     * Execute the command with optional console help interception.
     *
     * If the host class supports console help interception, the resolver
     * and renderer will be invoked before executing the command.
     *
     * Execution will be stopped if interception returns false.
     *
     * @param Api $telegram Telegram SDK API instance.
     * @param Update $update Incoming Telegram update.
     * @param array $entity Handler entity context.
     * @return mixed
     */
    public function make(Api $telegram, Update $update, array $entity): mixed
    {
        if (method_exists($this, "shouldInterceptConsoleHelp") && $this->shouldInterceptConsoleHelp()) {
            $continue = $this->beforeHandleIntercept($telegram, $update, $entity);

            if ($continue === false) {
                return null;
            }
        }

        return parent::make($telegram, $update, $entity);
    }
}
