<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Scan;

use DarkPeople\TelegramBot\Support\Scan\DiscoveredScan;

final class DiscoveredMiddleware
{
    /**
     * @param string[]|null $events
     * @param string[]|null $commands
     */
    public function __construct(
        public readonly DiscoveredScan $scan,

        // Middleware authoring metadata (optional)
        public readonly ?array $events = null,
        public readonly ?array $forBot = null,
        public readonly ?array $commands = null,
    ) {}

    /**
     * Convenience accessor supaya tetap enak dipakai seperti sebelumnya.
     *
     * @return class-string
     */
    public function class(): string
    {
        return $this->scan->class;
    }

    public function sourceType(): string
    {
        return $this->scan->sourceType;
    }

    public function sourcePath(): string
    {
        return $this->scan->sourcePath;
    }
}
