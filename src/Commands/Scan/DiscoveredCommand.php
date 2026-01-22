<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Commands\Scan;

use DarkPeople\TelegramBot\Support\Scan\DiscoveredScan;

final class DiscoveredCommand
{
    /**
     * @param string[]|null $forBot null = global
     * @param string[]|null $groups
     */
    public function __construct(
        public DiscoveredScan $scan,
        public ?array $forBot = null,
        public ?array $groups = null,
        public ?string $sharedAs = null,
    ) {}

    /**
     * @return class-string
     */
    public function class(): string
    {
        return $this->scan->class;
    }

    public function sourcePath(): string
    {
        return $this->scan->sourcePath;
    }
}
