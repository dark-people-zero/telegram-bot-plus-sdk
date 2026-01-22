<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Support\Scan;

final class DiscoveredScan
{
    /**
     * @param class-string $class
     * @param non-empty-string $sourceType
     * @param non-empty-string $sourcePath
     */
    public function __construct(
        public string $class,
        public string $sourceType,
        public string $sourcePath,
    ) {}
}
