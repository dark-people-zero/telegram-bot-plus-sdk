<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Authoring;

use DarkPeople\TelegramBot\Middleware\Contracts\TelegramMiddleware;
use ReflectionClass;
use RuntimeException;

final class MiddlewareAuthoringConfigNormalizer
{
    /**
     * @param array{middleware?: mixed, bots?: mixed} $telegramConfig
     */
    public function normalize(array $telegramConfig): AuthoringMiddlewareConfig
    {
        $rules = [];

        // 1) bots.{bot}.middleware (STRICT)
        $bots = $telegramConfig['bots'] ?? [];
        if (!is_array($bots)) {
            throw new RuntimeException("Invalid telegram.bots config: expected array.");
        }

        foreach ($bots as $botName => $botConfig) {
            if (!is_string($botName) || trim($botName) === '') continue;

            if (!is_array($botConfig)) {
                throw new RuntimeException("Invalid telegram.bots.{$botName} config: expected array.");
            }

            if (!array_key_exists('middleware', $botConfig)) {
                continue;
            }

            $rules = array_merge(
                $rules,
                $this->normalizeMiddlewareNode(
                    node: $botConfig['middleware'],
                    defaultForBot: [trim($botName)],
                    sourceType: 'config:bot',
                    mode: 'bot_strict'
                )
            );
        }

        // 2) telegram.middleware (GLOBAL)
        if (array_key_exists('middleware', $telegramConfig)) {
            $rules = array_merge(
                $rules,
                $this->normalizeMiddlewareNode(
                    node: $telegramConfig['middleware'],
                    defaultForBot: null,
                    sourceType: 'config:global',
                    mode: 'global'
                )
            );
        }

        return new AuthoringMiddlewareConfig($rules);
    }

    /**
     * Node middleware HARUS event-map.
     *
     * mode:
     * - bot_strict: item hanya string class
     * - global: item boleh string class atau array rule {class, forBot?}
     *
     * @return AuthoringMiddlewareRule[]
     */
    private function normalizeMiddlewareNode( mixed $node, ?array $defaultForBot, string $sourceType, string $mode ): array {
        if (is_null($node)) return [];

        if (!is_array($node)) {
            throw new RuntimeException("Invalid {$sourceType} middleware config: expected event-map array.");
        }

        $rules = [];

        foreach ($node as $eventKey => $value) {
            if (!is_string($eventKey) || trim($eventKey) === '') {
                // tegas: event-map wajib string key
                throw new RuntimeException("Invalid {$sourceType} middleware config: event key must be non-empty string.");
            }

            $eventKey = trim($eventKey);

            if ($eventKey === 'command') {
                if (!is_array($value)) {
                    throw new RuntimeException("Invalid {$sourceType} middleware 'command' config: expected array.");
                }

                foreach ($value as $commandKey => $items) {
                    if (!is_string($commandKey) || trim($commandKey) === '') {
                        throw new RuntimeException("Invalid {$sourceType} middleware 'command' config: command key must be non-empty string.");
                    }

                    $commandKey = trim($commandKey);

                    $rules = array_merge(
                        $rules,
                        $this->normalizeItemsToRules(
                            items: $items,
                            event: 'command',
                            commands: [$commandKey],
                            defaultForBot: $defaultForBot,
                            sourceType: $sourceType,
                            ctx: "{$sourceType} middleware command '{$commandKey}'",
                            mode: $mode
                        )
                    );
                }

                continue;
            }

            $rules = array_merge(
                $rules,
                $this->normalizeItemsToRules(
                    items: $value,
                    event: $eventKey,
                    commands: null,
                    defaultForBot: $defaultForBot,
                    sourceType: $sourceType,
                    ctx: "{$sourceType} middleware event '{$eventKey}'",
                    mode: $mode
                )
            );
        }

        return $rules;
    }

    /**
     * mode:
     * - bot_strict: items must be list of string class
     * - global: items can be string class OR array rule {class, forBot?}
     *
     * @param string[]|null $commands
     * @return AuthoringMiddlewareRule[]
     */
    private function normalizeItemsToRules(
        mixed $items,
        string $event,
        ?array $commands,
        ?array $defaultForBot,
        string $sourceType,
        string $ctx,
        string $mode
    ): array {
        if (is_null($items)) return [];

        if (!is_array($items)) {
            throw new RuntimeException("Invalid {$ctx}: expected array.");
        }

        $rules = [];
        $seen = []; // class-string => true

        foreach ($items as $key => $item) {
            // TEGAS: kita tidak dukung "class sebagai key" (biar tidak ambigu)
            if (is_string($key) && trim($key) !== '') {
                throw new RuntimeException("Invalid {$ctx}: associative array is not allowed (class as key is not supported).");
            }

            $class = null;
            $forBot = $defaultForBot;

            if (is_string($item)) {
                // bot_strict & global: ok
                $class = trim($item);
            } elseif (is_array($item)) {
                // bot_strict: tidak boleh ada rule array
                if ($mode === 'bot_strict') {
                    throw new RuntimeException("Invalid {$ctx}: bot middleware items must be class strings only (rule array is not allowed).");
                }

                // global: rule array allowed
                if (!array_key_exists('class', $item) || !is_string($item['class'])) {
                    throw new RuntimeException("Invalid {$ctx}: rule item array must have string key 'class'.");
                }

                $class = trim($item['class']);

                if (array_key_exists('forBot', $item)) {
                    $forBot = $this->normalizeForBotValue(
                        $item['forBot'],
                        "{$ctx} forBot for class '{$class}'"
                    );
                }
            } else {
                throw new RuntimeException("Invalid {$ctx}: middleware item must be string (or array rule in global only).");
            }

            if ($class === '') continue;

            // fail fast: class must exist
            if (!class_exists($class)) {
                throw new RuntimeException("Invalid {$ctx}: class not autoloadable: {$class}");
            }

            // duplicate detection in the same list
            if (isset($seen[$class])) {
                throw new RuntimeException("Duplicate {$ctx}: {$class}");
            }
            $seen[$class] = true;

            $ref = new ReflectionClass($class);

            // skip abstract
            if ($ref->isAbstract()) {
                continue;
            }

            // skip not TelegramMiddleware
            if (!$ref->implementsInterface(TelegramMiddleware::class)) {
                continue;
            }

            $rules[] = new AuthoringMiddlewareRule(
                class: $class,
                event: $event,
                forBot: $forBot,
                commands: $commands,
                sourceType: $sourceType
            );
        }

        return $rules;
    }

    /**
     * forBot:
     * - null => null
     * - string => ['bot']
     * - array => ['botA','botB']
     * - empty => null
     */
    private function normalizeForBotValue(mixed $value, string $ctx): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            $v = trim($value);
            return $v === '' ? null : [$v];
        }

        if (is_array($value)) {
            $list = array_map(static fn ($v) => is_string($v) ? trim($v) : '', $value);
            $list = array_values(array_filter($list, static fn (string $v) => $v !== ''));
            return $list ?: null;
        }

        throw new RuntimeException("Invalid {$ctx}: forBot must be string|array|null.");
    }
}
