<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Support\BaseArraySerializable;

/**
 * Represents a node in the Telegram command tree.
 *
 * A command node may act as:
 * - a virtual/group node (no command class), or
 * - a leaf command node (with a concrete command class).
 *
 * Nodes form a hierarchical tree used for command resolution,
 * authorization checks, and help rendering.
 *
 * This object is cache-safe and serializable via BaseArraySerializable.
 * Parent references are excluded from serialization and rebuilt
 * after deserialization.
 */
final class CommandNode extends BaseArraySerializable
{
    /**
     * @param string                       $name         Command name or full path
     *                                                  (e.g. "make", "make:seed").
     * @param string|null                  $description  Human-readable description.
     * @param class-string|null            $commandClass Concrete command class for leaf nodes;
     *                                                  null for virtual/group nodes.
     * @param CommandNode|null             $parent       Parent command node (volatile, not cached).
     * @param array<int, ArgumentSpec>     $arguments    Argument specifications for this command.
     * @param array<int, OptionSpec>       $options      Option specifications for this command.
     * @param array<string, CommandNode>   $children     Child command nodes,
     *                                                  keyed by subcommand short name
     *                                                  (e.g. "seed").
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $commandClass = null,
        public ?CommandNode $parent = null,
        public array $arguments = [],
        public array $options = [],
        public array $children = [],
    ) {}

    /**
     * Define properties that must be excluded from serialization.
     *
     * The parent reference is excluded because it is volatile and
     * rebuilt after deserialization.
     *
     * @return array<int, string>
     */
    protected static function exclude(): array
    {
        return ['parent'];
    }

    /**
     * Restore volatile properties after deserialization.
     *
     * This method relinks parent pointers for all child nodes
     * by traversing the command tree recursively.
     */
    protected function afterDeserialize(): void
    {
        // relink parent pointers setelah hydrate children
        $this->relinkTreeParents(
            childrenGetter: fn (CommandNode $n): array => ($n instanceof self) ? $n->children : [],
            parentSetter: fn (CommandNode $n, ?CommandNode $p): CommandNode|null => (
                $n instanceof self ? $n->parent = ($p instanceof self ? $p : null) : null
            ),
            parent: null
        );
    }

    /**
     * Generic helper to relink parent references in a tree structure.
     *
     * This method traverses the tree depth-first and assigns
     * parent pointers using the provided callbacks.
     *
     * Intended to be called from afterDeserialize().
     *
     * @param callable(CommandNode): iterable<CommandNode> $childrenGetter
     *        Callback used to retrieve child nodes.
     * @param callable(CommandNode, ?CommandNode): void $parentSetter
     *        Callback used to assign the parent node.
     * @param CommandNode|null $parent The parent node to assign.
     */
    final protected function relinkTreeParents(
        callable $childrenGetter,
        callable $parentSetter,
        ?CommandNode $parent = null
    ): void {
        $parentSetter($this, $parent);

        foreach ($childrenGetter($this) as $child) {
            if ($child instanceof CommandNode) {
                $child->relinkTreeParents($childrenGetter, $parentSetter, $this);
            }
        }
    }
}
