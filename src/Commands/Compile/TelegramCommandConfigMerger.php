<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Commands\Compile;

use DarkPeople\TelegramBot\Commands\Scan\DiscoveredCommand;
use RuntimeException;

final class TelegramCommandConfigMerger
{
    /**
     * @param array $telegramConfig
     * @param DiscoveredCommand[] $discovered
     * @return array merged telegram config
     */
    public function merge(array $telegramConfig, array $discovered): array
    {
        foreach ($discovered as $cmd) {
            if (!$cmd instanceof DiscoveredCommand) continue;

            $class = $cmd->class();

            // register shared command if needed
            $ref = $class;
            if ($cmd->sharedAs !== null) {
                $key = $cmd->sharedAs;

                $existing = $telegramConfig['shared_commands'][$key] ?? null;
                if ($existing !== null && $existing !== $class) {
                    throw new RuntimeException("shared_commands key '{$key}' already mapped to '{$existing}', cannot remap to '{$class}'.");
                }

                $telegramConfig['shared_commands'][$key] = $class;
                $ref = $key; // IMPORTANT: use shared key when registering into lists
            }

            // groups
            if (!empty($cmd->groups)) {
                foreach ($cmd->groups as $group) {
                    $group = is_string($group) ? trim($group) : '';
                    if ($group === '') continue;

                    $telegramConfig['command_group'][$group] = $this->appendUnique(
                        (array) ($telegramConfig['command_group'][$group] ?? []),
                        [$ref]
                    );
                }
            }

            // global vs bot
            if ($cmd->forBot === null) {
                $telegramConfig['commands'] = $this->appendUnique(
                    (array) ($telegramConfig['commands'] ?? []),
                    [$ref]
                );
            } else {
                foreach ($cmd->forBot as $bot) {
                    $bot = is_string($bot) ? trim($bot) : '';
                    if ($bot === '') continue;

                    $path = "bots.$bot.commands";
                    $current = (array) data_get($telegramConfig, $path, []);
                    $current = $this->appendUnique($current, [$ref]);
                    data_set($telegramConfig, $path, $current);
                }
            }
        }

        return $telegramConfig;
    }

    /**
     * Append + dedup preserve order (first wins).
     *
     * @param array $list
     * @param array $toAppend
     * @return array
     */
    private function appendUnique(array $list, array $toAppend): array
    {
        $seen = [];
        $out = [];

        foreach ($list as $item) {
            if (!is_string($item) || $item === '') continue;
            if (isset($seen[$item])) continue;
            $seen[$item] = true;
            $out[] = $item;
        }

        foreach ($toAppend as $item) {
            if (!is_string($item) || $item === '') continue;
            if (isset($seen[$item])) continue;
            $seen[$item] = true;
            $out[] = $item;
        }

        return $out;
    }
}
