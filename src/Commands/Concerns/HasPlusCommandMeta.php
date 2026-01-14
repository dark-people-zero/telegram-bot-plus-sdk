<?php

namespace DarkPeople\TelegramBot\Commands\Concerns;

trait HasPlusCommandMeta
{
    public bool $autoRegister = false;
    public string|array|null $forBot = null;
    public ?array $groups = null;
    public ?string $sharedAs = null;

    public function autoRegister(): bool { return (bool) $this->autoRegister; }
    public function forBot(): string|array|null { return $this->forBot; }
    public function groups(): ?array { return $this->groups; }
    public function sharedAs(): ?string { return $this->sharedAs; }
}
