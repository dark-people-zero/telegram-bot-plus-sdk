<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Compile;

use DarkPeople\TelegramBot\Middleware\Authoring\MiddlewareAuthoringConfigNormalizer;
use DarkPeople\TelegramBot\Middleware\Scan\MiddlewareScanner;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

final class MiddlewareConfigBuilder
{
    public function __construct(
        private MiddlewareScanner $scanner,
        private MiddlewareAuthoringConfigNormalizer $normalizer,
        private MiddlewareCompiler $compiler,
        private ConfigRepository $config,
    ) {}

    /**
     * Build compiled middleware_class config (system layer).
     *
     * @return array<string, array<string, mixed>>
     */
    public function build(): array
    {
        /** @var array $telegramConfig */
        $telegramConfig = (array) $this->config->get('telegram', []);

        $paths = $this->middlewareScanPaths($telegramConfig);
        $botNames = $this->botNames($telegramConfig);

        $scanned = $this->scanner->scan($paths);
        $authoring = $this->normalizer->normalize($telegramConfig);

        return $this->compiler->compile($scanned, $authoring, $botNames);
    }

    /**
     * @param array $telegramConfig
     * @return string[]
     */
    private function middlewareScanPaths(array $telegramConfig): array
    {
        $paths = $telegramConfig['middleware_scan_paths'] ?? app_path('Telegram/Middleware');

        if (is_string($paths)) {
            $paths = [$paths];
        } elseif (!is_array($paths)) {
            throw new RuntimeException('telegram.middleware_scan_paths must be string or array of string.');
        }

        $out = [];
        foreach ($paths as $path) {
            if (!is_string($path)) continue;
            $path = trim($path);
            if ($path === '') continue;
            $out[] = $path;
        }

        return $out;
    }

    /**
     * @param array $telegramConfig
     * @return string[]
     */
    private function botNames(array $telegramConfig): array
    {
        $bots = $telegramConfig['bots'] ?? [];
        if (!is_array($bots)) return [];

        $names = array_keys($bots);

        // normalize string-only
        $out = [];
        foreach ($names as $name) {
            if (!is_string($name)) continue;
            $name = trim($name);
            if ($name === '') continue;
            $out[] = $name;
        }

        return $out;
    }
}
