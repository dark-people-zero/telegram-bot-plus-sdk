<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Contracts;

interface PlusMiddlewareMeta
{
    /**
     * Daftar event key.
     * Contoh: ['message','callback_query'] atau ['command'] atau ['messages'] (group).
     *
     * @return string[]
     */
    public function events(): array;

    /**
     * null = global, string = bot name
     */
    public function forBot(): string|array|null;

    /**
     * Jika middleware ini untuk event 'command':
     * - return ['start','help'] untuk command tertentu
     * - return ['*'] untuk semua command
     *
     * Return null bila tidak relevan / tidak didefinisikan di class.
     *
     * @return string[]|null
     */
    public function commands(): ?array;
}
