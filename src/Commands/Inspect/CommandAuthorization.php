<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Support\UpdateMeta\TelegramUpdateMeta;

/**
 * Resolve command authorization by traversing a command node tree.
 *
 * This class determines whether a command (or any of its descendant commands)
 * is authorized to be executed for the current Telegram update.
 *
 * Authorization is performed by invoking the static authorize() method
 * on command classes found within the node hierarchy.
 */
class CommandAuthorization
{
    /**
     * Determine whether the given command node is authorized.
     *
     * If the node does not define a command class directly, all descendant
     * command classes will be checked. Authorization succeeds if at least
     * one command class returns true.
     *
     * @param CommandNode $node The root command node to evaluate.
     * @return bool True if any command in the subtree is authorized; false otherwise.
     */
    public static function authorizeCommand(CommandNode $node): bool
    {
        foreach (self::findCommandClass($node) as $className) {
            $authorize = $className::authorize(app(TelegramUpdateMeta::class));
            if ($authorize) return true;
        }
        return false;
    }

    /**
     * Collect all command classes within the given command node subtree.
     *
     * If the current node defines a command class, it will be included.
     * Otherwise, the method recursively traverses all child nodes.
     *
     * @param CommandNode $node   The command node to traverse.
     * @param array<int, class-string> $result Internal accumulator for recursion.
     * @return array<int, class-string> A list of command class names.
     */
    private static function findCommandClass(CommandNode $node, array $result = []): array
    {
        if($node->commandClass !== null) {
            $result[] = $node->commandClass;
            return $result;
        }

        foreach ($node->children as $child) {
            $r = self::findCommandClass($child, $result);
            $result = array_merge($result, $r);
        }

        return $result;
    }

}