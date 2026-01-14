<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Compile;

use DarkPeople\TelegramBot\Contracts\TelegramEvents;
use DarkPeople\TelegramBot\Middleware\Authoring\AuthoringMiddlewareConfig;
use DarkPeople\TelegramBot\Middleware\Authoring\AuthoringMiddlewareRule;
use DarkPeople\TelegramBot\Middleware\Scan\DiscoveredMiddleware;
use RuntimeException;

final class MiddlewareCompiler
{

    /**
     * @param DiscoveredMiddleware[] $scanned
     * @param string[] $botNames
     * @return array<string, array<string, mixed>>
     *
     * Output:
     * [
     *   bot => [
     *     event => [class...],
     *     'command' => [
     *        '__all__' => [class...],
     *        'start' => [class...],
     *     ],
     *   ],
     * ]
     */
    public function compile(array $scanned, AuthoringMiddlewareConfig $config, array $botNames): array
    {
        $out = [];

        foreach ($botNames as $bot) {
            $bot = is_string($bot) ? trim($bot) : '';
            if ($bot === '') continue;

            $out[$bot] = $this->initBotBuckets();

            // 1) scan single
            $this->applyScanned($out[$bot], $bot, $scanned, pass: 'single');
            // 2) scan group
            $this->applyScanned($out[$bot], $bot, $scanned, pass: 'group');

            // 3) config:bot single
            $this->applyConfig($out[$bot], $bot, $config->rules, sourceType: 'config:bot', pass: 'single');
            // 4) config:bot group
            $this->applyConfig($out[$bot], $bot, $config->rules, sourceType: 'config:bot', pass: 'group');

            // 5) config:global single
            $this->applyConfig($out[$bot], $bot, $config->rules, sourceType: 'config:global', pass: 'single');
            // 6) config:global group
            $this->applyConfig($out[$bot], $bot, $config->rules, sourceType: 'config:global', pass: 'group');

            // Dedup final per bucket (preserve order)
            $this->dedupBotBuckets($out[$bot]);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function initBotBuckets(): array
    {
        $b = [];
        foreach (TelegramEvents::EVENTS as $ev) {
            $b[$ev] = [];
        }

        $b['command'] = []; // will be filled lazily with '__all__' and named commands
        return $b;
    }

    /**
     * @param array<string, mixed> $botBuckets
     * @param DiscoveredMiddleware[] $scanned
     */
    private function applyScanned(array &$botBuckets, string $bot, array $scanned, string $pass): void
    {
        foreach ($scanned as $mw) {
            // class always exists in scan object; metadata optional
            $events = $mw->events ?? null;
            if (!$events) continue;

            // filter by bot scope from scan metadata
            if (!$this->ruleAppliesToBot($mw->forBot ?? null, $bot)) {
                continue;
            }

            $singleEvents = [];
            $groupEvents = [];

            foreach ($events as $evKey) {
                $evKey = is_string($evKey) ? trim($evKey) : '';
                if ($evKey === '') continue;

                if ($evKey === 'command') {
                    // treat command as "single pass"
                    $singleEvents[] = 'command';
                    continue;
                }

                if (in_array($evKey, TelegramEvents::EVENTS, true)) {
                    $singleEvents[] = $evKey;
                    continue;
                }

                if (array_key_exists($evKey, TelegramEvents::GROUPS)) {
                    $groupEvents[] = $evKey;
                    continue;
                }

                throw new RuntimeException("Unknown scanned middleware event key '{$evKey}' on {$mw->class()} ({$mw->sourcePath()}).");
            }

            if ($pass === 'single') {
                foreach ($singleEvents as $evKey) {
                    $this->appendMiddlewareForEvent($botBuckets, $mw->class(), $evKey, $mw->commands ?? null);
                }
            } elseif ($pass === 'group') {
                foreach ($groupEvents as $groupKey) {
                    foreach (TelegramEvents::GROUPS[$groupKey] as $expanded) {
                        $this->appendMiddlewareForEvent($botBuckets, $mw->class(), $expanded, null);
                    }
                }
            } else {
                throw new RuntimeException("Invalid pass '{$pass}'");
            }
        }
    }

    /**
     * @param array<string, mixed> $botBuckets
     * @param AuthoringMiddlewareRule[] $rules
     */
    private function applyConfig(
        array &$botBuckets,
        string $bot,
        array $rules,
        string $sourceType,
        string $pass
    ): void {
        foreach ($rules as $rule) {
            if (!$rule instanceof AuthoringMiddlewareRule) continue;
            if ($rule->sourceType !== $sourceType) continue;

            if (!$this->ruleAppliesToBot($rule->forBot, $bot)) {
                continue;
            }

            $eventKey = trim($rule->event);

            if ($eventKey === 'command') {
                // command is processed only in "single" pass
                if ($pass !== 'single') continue;

                $this->appendMiddlewareForEvent($botBuckets, $rule->class, 'command', $rule->commands);
                continue;
            }

            if (in_array($eventKey, TelegramEvents::EVENTS, true)) {
                if ($pass !== 'single') continue;

                $this->appendMiddlewareForEvent($botBuckets, $rule->class, $eventKey, null);
                continue;
            }

            if (array_key_exists($eventKey, TelegramEvents::GROUPS)) {
                if ($pass !== 'group') continue;

                foreach (TelegramEvents::GROUPS[$eventKey] as $expanded) {
                    $this->appendMiddlewareForEvent($botBuckets, $rule->class, $expanded, null);
                }
                continue;
            }

            throw new RuntimeException("Unknown {$sourceType} middleware event key '{$eventKey}' on class {$rule->class}.");
        }
    }

    /**
     * Append middleware class into correct bucket.
     *
     * @param array<string, mixed> $botBuckets
     * @param class-string $class
     * @param string $eventKey either concrete event or 'command'
     * @param string[]|null $commands only meaningful when $eventKey === 'command'
     */
    private function appendMiddlewareForEvent(array &$botBuckets, string $class, string $eventKey, ?array $commands): void
    {
        if ($eventKey === 'command') {
            if (!isset($botBuckets['command']) || !is_array($botBuckets['command'])) {
                $botBuckets['command'] = [];
            }

            // If commands not provided, treat as all commands
            $commands = $commands ?? ['*'];
            if ($commands === []) {
                $commands = ['*'];
            }

            foreach ($commands as $cmd) {
                $cmd = is_string($cmd) ? trim($cmd) : '';
                if ($cmd === '') continue;

                $bucket = ($cmd === '*') ? '__all__' : $cmd;

                if (!isset($botBuckets['command'][$bucket]) || !is_array($botBuckets['command'][$bucket])) {
                    $botBuckets['command'][$bucket] = [];
                }

                $botBuckets['command'][$bucket][] = $class;
            }

            return;
        }

        // concrete event
        if (!array_key_exists($eventKey, $botBuckets)) {
            // Should not happen if EVENTS init is correct, but keep fail fast
            throw new RuntimeException("Compiler internal error: unknown concrete event bucket '{$eventKey}'.");
        }

        $botBuckets[$eventKey][] = $class;
    }

    /**
     * @param string[]|null $forBot
     */
    private function ruleAppliesToBot(?array $forBot, string $bot): bool
    {
        if ($forBot === null) return true;
        return in_array($bot, $forBot, true);
    }

    /**
     * Dedup per bucket, preserve first occurrence.
     *
     * @param array<string, mixed> $botBuckets
     */
    private function dedupBotBuckets(array &$botBuckets): void
    {
        foreach (TelegramEvents::EVENTS as $ev) {
            $botBuckets[$ev] = $this->dedupList($botBuckets[$ev] ?? []);
        }

        if (!isset($botBuckets['command']) || !is_array($botBuckets['command'])) {
            return;
        }

        foreach ($botBuckets['command'] as $cmd => $list) {
            if (!is_array($list)) continue;
            $botBuckets['command'][$cmd] = $this->dedupList($list);
        }
    }

    /**
     * @param mixed $list
     * @return string[]
     */
    private function dedupList(mixed $list): array
    {
        if (!is_array($list)) return [];

        $seen = [];
        $out = [];

        foreach ($list as $item) {
            if (!is_string($item) || $item === '') continue;
            if (isset($seen[$item])) continue;
            $seen[$item] = true;
            $out[] = $item;
        }

        return $out;
    }
}
