<?php

namespace DarkPeople\TelegramBot\Middleware\Contracts;

use DarkPeople\TelegramBot\Support\TelegramContext;

interface TelegramMiddleware
{
    /**
     * Return false untuk menghentikan pipeline.
     */
    public function handle(TelegramContext $context): bool;
}
