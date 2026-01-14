<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Commands\Contracts;

interface PlusCommandMeta
{
    public function autoRegister(): bool;
    
    /**
     * null => global (telegram.commands)
     * string/array => bot scope (telegram.bots.{bot}.commands)
     */
    public function forBot(): string|array|null;

    /**
     * Daftar group name untuk command_group.
     *
     * @return string[]
     */
    public function groups(): array;

    /**
     * Jika di-set, maka command ini diregister sebagai shared command dengan key ini.
     * Return null jika tidak.
     */
    public function sharedAs(): ?string;
}
