<?php

namespace DarkPeople\TelegramBot\Middleware\Concerns;

trait HasPlusMiddlewareMeta
{
    public function events(): array
    {
        /** @var array $events */
        $events = property_exists($this, 'events') ? $this->events : [];
        return (array) $events;
    }

    public function forBot(): string|array|null 
    {
        return property_exists($this, 'forBot') ? $this->forBot : null;
    }

    public function commands(): ?array 
    {
        return property_exists($this, 'commands') ? $this->commands : null;
    }
}
