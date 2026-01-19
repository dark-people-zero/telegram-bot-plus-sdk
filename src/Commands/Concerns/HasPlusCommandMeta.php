<?php

namespace DarkPeople\TelegramBot\Commands\Concerns;

trait HasPlusCommandMeta
{
    public function autoRegister(): bool
    {
        return (bool) (property_exists($this, "autoRegister") ? $this->autoRegister : false);
    }
    public function forBot(): string|array|null
    {
        return (property_exists($this, "forBot") ? $this->forBot : null);
    }
    public function groups(): array
    {
        return (property_exists($this, "groups") ? $this->groups ?? [] : []);
    }
    public function sharedAs(): ?string
    {
        return (property_exists($this, "sharedAs") ? $this->sharedAs : null);
    }
}
