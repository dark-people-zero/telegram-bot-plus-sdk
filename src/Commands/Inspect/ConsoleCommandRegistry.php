<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Commands\PlusCommand;
use DarkPeople\TelegramBot\Support\RuntimeConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Telegram\Bot\Api;

/**
 * Registry for compiled Telegram console commands.
 *
 * This registry stores the command tree as CommandNode instances and provides:
 * - lookup by command root name
 * - root node listing for help output
 * - global options (e.g. --help)
 *
 * The registry can be built from the SDK CommandBus and optionally cached.
 */
final class ConsoleCommandRegistry
{
    /**
     * Cache key used to persist the compiled command registry.
     *
     * @var string
     */
    public const CACHE_KEY = 'telegram:console_registry';

    /**
     * Root command nodes keyed by lowercased root name.
     *
     * Example keys: "make", "user", "seed"
     *
     * @var array<string, CommandNode>
     */
    private array $nodes = [];

    /**
     * Global options available for all commands.
     *
     * @var array<int, OptionSpec>
     */
    private array $globalOptions = [];

    /**
     * Create a new registry with default global options.
     */
    public function __construct()
    {
        $this->globalOptions = [
            new OptionSpec('--help', '-h', 'Display help for the command.', false),
        ];
    }

    /**
     * Get global options available for all commands.
     *
     * @return array<int, OptionSpec>
     */
    public function globalOptions(): array
    {
        return $this->globalOptions;
    }
    
    /**
     * Get all root nodes keyed internally by lowercased root name.
     *
     * @return array<string, CommandNode>
     */
    public function nodes() : array {
        return $this->nodes;
    }

    /**
     * Build a registry from the SDK CommandBus.
     *
     * Source of truth:
     * $telegram->getCommandBus()->getCommands()
     *
     * When runtime cache is enabled, the registry will be loaded/saved
     * using {@see self::CACHE_KEY}.
     *
     * @param Api $telegram Telegram SDK API instance.
     * @return self
     */
    public static function fromCommandBus(Api $telegram): self
    {
        $r = new self();

        if (RuntimeConfig::useCache()) {
            $payload = Cache::get(self::CACHE_KEY);
            if (is_array($payload)) {
                $r->nodes = array_map(fn ($a) => CommandNode::deserialize($a), $payload['nodes'] ?? []);
                $r->globalOptions = array_map(fn ($a) => OptionSpec::deserialize($a), $payload['globalOptions'] ?? []);
                return $r;
            }
        }

        $bus = $telegram->getCommandBus();
        $commands = is_object($bus) && method_exists($bus, 'getCommands') ? $bus->getCommands() : [];

        if (!is_array($commands)) return $r;

        // 1) register all commands in bus (leaf or group if user created it)
        foreach ($commands as $cmd) {
            $r->ingestCommand($cmd, null);
        }

        // --- save cache ---
        if (RuntimeConfig::useCache()) {
            $payload = [
                'nodes' => array_map(fn (CommandNode $n) => $n->serialize(), $r->nodes),
                'globalOptions' => array_map(fn (OptionSpec $o) => $o->serialize(), $r->globalOptions),
            ];

            Cache::forever(self::CACHE_KEY, $payload);
        }

        return $r;
    }

    /**
     * Ingest a command into the registry and attach it to the command tree.
     *
     * This method:
     * - parses argument/option patterns
     * - builds a node chain from command names (split by ":")
     * - attaches/merges the chain into the existing tree
     * - recursively ingests child commands (if any)
     *
     * @param PlusCommand $cmd
     * @param CommandNode|null $attachTo If provided, the command chain is attached under this node.
     * @return void
     */
    private function ingestCommand(PlusCommand $cmd, ?CommandNode $attachTo): void
    {
        if (!is_object($cmd)) return;
        if (!$cmd instanceof PlusCommand) return;

        $name = strtolower((string)($cmd->getName() ?? ''));
        if ($name === '') return;
        
        $desc = $cmd->getDescription();
        $pattern = $cmd->getPattern();

        $children = method_exists($cmd, 'getChildren') ? $cmd->getChildren() : [];
        if (!is_array($children)) $children = [];

        $arguments = PatternParser::parseArguments($pattern);

        if (method_exists($cmd, "getArgDesc") && is_array($cmd->getArgDesc())) {
            foreach ($arguments as $arg) {
                $args = $cmd->getArgDesc();
                if (isset($args[$arg->name]) && is_string($args[$arg->name])) {
                    $arg->description = $args[$arg->name];
                }
            }
        }

        $patternOption = '';
        if(method_exists($cmd, "getPatternOption")) $patternOption = $cmd->getPatternOption();

        $options = PatternParser::parseOptions($patternOption);

        if (method_exists($cmd, "getOptionDesc") && is_array($cmd->getOptionDesc())) {
            foreach ($options as $opt) {
                $option = $cmd->getOptionDesc();
                $nm = str_replace("--", '', $opt->long);
                if (isset($option[$nm]) && is_string($option[$nm])) {
                    $opt->description = $option[$nm];
                }
            }
        }

        $promptValue = [];
        if (method_exists($cmd, "getPromptValue")) $promptValue = $cmd->getPromptValue() ?? [];

        $promptVarible = [];
        if (method_exists($cmd, "getPromptVarible")) $promptVarible = $cmd->getPromptVarible() ?? [];
            

        [$incomingRoot, $incomingLeaf] = $this->buildChainFromNames(
            Str::of($name)->explode(':')->all(),
            [
                'description' => $desc,
                'commandClass' => get_class($cmd), // atau dari $cmd
                'arguments' => $arguments ?? [],
                'options' => $options ?? [],
                'promptValue' => $promptValue ?? [],
                'promptVarible' => $promptVarible ?? [],
            ]
        );

        // 1) attach ke root global atau ke node tertentu
        if ($attachTo === null) {
            // global root map: $this->node[name]
            $rootName = $incomingRoot->name;

            if (!isset($this->nodes[$rootName])) {
                $this->nodes[$rootName] = $incomingRoot;
                $leafInTree = $incomingLeaf;
            } else {
                // attach chain di bawah "virtual root" untuk global
                $virtual = new CommandNode(name: '__virtual__');
                $virtual->children[$this->nodes[$rootName]->name] = $this->nodes[$rootName];

                $leafInTree = $this->attachChainToParent($virtual, $incomingRoot);
                $this->nodes[$rootName] = $virtual->children[$rootName];
            }
        } else {
            $leafInTree = $this->attachChainToParent($attachTo, $incomingRoot);
        }

        // 2) ingest children class ke leaf node
        foreach ($children as $childClass) {
            $childCmd = new $childClass(); // atau container resolve sesuai arsitektur kamu
            $this->ingestCommand($childCmd, $leafInTree);
        }
    }

    /**
     * Build a single-path CommandNode chain from command name segments.
     *
     * Example:
     * ["make", "seed"] will build:
     * make -> seed (leaf)
     *
     * Leaf metadata (description/class/args/options) is applied only to the leaf node.
     *
     * @param array<int, string> $names Command name segments.
     * @param array<string, mixed> $leafMeta Leaf metadata:
     *                                     - description?: string|null
     *                                     - commandClass?: class-string|null
     *                                     - arguments?: array<int, ArgumentSpec>
     *                                     - options?: array<int, OptionSpec>
     * @return array{0: CommandNode, 1: CommandNode} [root, leaf]
     */
    private function buildChainFromNames(array $names, array $leafMeta = []): array
    {
        $root = null;
        $prev = null;

        foreach ($names as $i => $name) {
            $isLeaf = ($i === count($names) - 1);

            $node = new CommandNode(
                name: $name,
                description: $isLeaf ? ($leafMeta['description'] ?? null) : null,
                commandClass: $isLeaf ? ($leafMeta['commandClass'] ?? null) : null,
                parent: $prev,
                arguments: $isLeaf ? ($leafMeta['arguments'] ?? []) : [],
                options: $isLeaf ? ($leafMeta['options'] ?? []) : [],
                children: [],
                promptValue: $leafMeta['promptValue'] ?? [],
                promptVarible: $leafMeta['promptVarible'] ?? [],
            );

            if ($prev) {
                $prev->children[$node->name] = $node;
            } else {
                $root = $node;
            }

            $prev = $node;
        }

        return [$root, $prev]; // [$root, $leaf]
    }

    /**
     * Attach an incoming single-path chain under a parent node, merging by name.
     *
     * Existing nodes are reused; missing nodes are created by attaching the
     * incoming nodes into the tree. Returns the leaf node within the registry tree.
     *
     * @param CommandNode $parent The node to attach under.
     * @param CommandNode $incomingRoot The root node of the incoming chain.
     * @return CommandNode The leaf node in the registry tree.
     */
    private function attachChainToParent(CommandNode $parent, CommandNode $incomingRoot): CommandNode
    {
        $cursor = $parent;          // kita lagi "nempel" di node mana dalam tree existing
        $incoming = $incomingRoot;  // node chain yang sedang kita proses

        while ($incoming) {
            $name = $incoming->name;

            // 1) Pastikan child bernama $name ada di cursor
            if (!isset($cursor->children[$name])) {
                // belum ada -> tempel node incoming ke tree
                $cursor->children[$name] = $incoming;
                $incoming->parent = $cursor;
            }

            // 2) Sekarang pindah cursor ke node existing (yang ada di tree)
            $cursor = $cursor->children[$name];

            // 3) Maju ke node berikutnya dalam chain incoming (karena single-path)
            $incoming = empty($incoming->children)
                ? null
                : reset($incoming->children); // ambil satu-satunya child node
        }

        return $cursor; // leaf node di tree (existing atau baru dibuat)
    }


}
