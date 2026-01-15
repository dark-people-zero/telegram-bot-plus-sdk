<?php

namespace DarkPeople\TelegramBot\Support;

use Illuminate\Support\Str;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

final class TelegramContext
{
    public readonly ?string $eventName;
    public readonly ?string $text;
    public readonly ?string $commandName;
    public readonly ?array $arguments;
    public readonly bool $isCommand;
    public readonly ?object $message;

    public function __construct(
        public readonly Api $telegram,
        public readonly Update $update,
        public readonly string $botName,
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

        $this->text = is_object($this->message) ? ($this->message?->text ?? null) : null;

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

