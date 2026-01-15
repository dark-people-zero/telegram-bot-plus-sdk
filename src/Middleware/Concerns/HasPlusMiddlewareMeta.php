<?php

namespace DarkPeople\TelegramBot\Middleware\Concerns;

trait HasPlusMiddlewareMeta
{
    public array $events = [];         // wajib
    public string|array|null $forBot = null;     // optional
    public ?array $commands = null;    // optional

    public function events(): array { return (array) $this->events; }
    public function forBot(): string|array|null { return $this->forBot; }
    public function commands(): ?array { return $this->commands; }
}
