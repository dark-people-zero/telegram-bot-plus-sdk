<?php

namespace DarkPeople\TelegramBot\Support;

use Illuminate\Support\Str;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

final class TelegramContext
{
    public ?string $eventName;
    public ?string $text;
    public ?string $commandName;
    public ?array $arguments;
    public bool $isCommand;
    public ?object $message;

    public function __construct(
        public Api $telegram,
        public Update $update,
        public string $botName,
    ) {
        $this->resolveProperty();
    }

    private function resolveProperty(): void
    {
        $this->message = $this->update->getMessage();

        $this->eventName = Str::snake((string) $this->update->objectType());

        $this->isCommand = is_object($this->message)
            && method_exists($this->message, 'hasCommand')
            && $this->message->hasCommand() === true;

        $this->text = data_get($this->message, 'text');

        $this->commandName = null;
        $this->arguments = null;

        if ($this->isCommand && !empty($this->text)) {
            $parts = Str::of(ltrim($this->text, '/'))->explode(' ');

            $cmd = (string) Str::of((string) $parts->first())->explode('@')->first();
            $cmd = Str::lower(trim($cmd));

            $parts->shift();
            $args = $parts->filter(fn ($v) => trim((string) $v) !== '')->values()->all();

            $this->commandName = $cmd !== '' ? $cmd : null;
            $this->arguments = $args ?: null;
        }
    }

    public function update(): Update {
        return $this->update;
    }
}

