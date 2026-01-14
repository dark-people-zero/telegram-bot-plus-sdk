<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Commands\Compile;

use DarkPeople\TelegramBot\Commands\Scan\CommandScanner;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

final class CommandConfigBuilder
{
    public function __construct(
        private CommandScanner $scanner,
        private TelegramCommandConfigMerger $merger,
        private ConfigRepository $config,
    ) {}

    /**
     * Build merged telegram config (SDK layer keys: commands, bots.*.commands, command_group, shared_commands).
     *
     * @return array
     */
    public function build(): array
    {
        /** @var array $telegramConfig */
        $telegramConfig = (array) $this->config->get('telegram', []);

        $paths = $this->commandScanPaths($telegramConfig);
        $discovered = $this->scanner->scan($paths);

        if (empty($discovered)) {
            return $telegramConfig;
        }

        return $this->merger->merge($telegramConfig, $discovered);
    }

    /**
     * @param array $telegramConfig
     * @return string[]
     */
    private function commandScanPaths(array $telegramConfig): array
    {
        $paths = $telegramConfig['command_scan_paths'] ?? app_path('Telegram/Commands');

        if (is_string($paths)) {
            $paths = [$paths];
        } elseif (!is_array($paths)) {
            throw new RuntimeException("telegram.command_scan_paths must be string or array of string.");
        }

        $out = [];
        foreach ($paths as $p) {
            if (!is_string($p)) continue;
            $p = trim($p);
            if ($p === '') continue;
            $out[] = $p;
        }

        return $out;
    }
}
