<?php

namespace DarkPeople\TelegramBot\Provider;

use Illuminate\Support\ServiceProvider;

final class TelegramBotRouteServiceProvider extends ServiceProvider
{
    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/../routes/telegram.php');
    }
}

